<?php
/* (c) Blue Wave Projects and Services 2015-2022. This software is released under the GNU GPL license.

 This is a FAS script providing an example of remote Forward Authentication for openNDS (NDS) on an http web server supporting PHP.

 The following NDS configurations must be set:
 1. fasport: Set to the port number the remote webserver is using (typically port 80)

 2. faspath: This is the path from the FAS Web Root to the location of this FAS script (not from the file system root).
	eg. /nds/fas-hid.php

 3. fasremoteip: The remote IPv4 address of the remote server eg. 46.32.240.41

 4. fasremotefqdn: The fully qualified domain name of the remote web server.
	This is required in the case of a shared web server (ie. a server that hosts multiple domains on a single IP),
	but is optional for a dedicated web server (ie. a server that hosts only a single domain on a single IP).
	eg. onboard-wifi.net

 5. faskey: Matching $key as set in this script (see below this introduction).
	This is a key phrase for NDS to encrypt the query string sent to FAS.
	It can be any combination of A-Z, a-z and 0-9, up to 16 characters with no white space.
	eg 1234567890

 6. fas_secure_enabled:  set to level 1
	The NDS parameters: clientip, clientmac, gatewayname, hid and redir
	are passed to FAS in the query string.


 This script requires the client user to enter their username and password address. This information is stored in a log file kept
 in /tmp or the same folder as this script.

 This script requests the client CPD to display the NDS splash.jpg image directly from the 
	/etc/opennds/htdocs/images folder of the NDS device.

 This script displays an example Terms of Service. You should modify this for your local legal juristiction

*/

// Allow immediate flush to browser
if (ob_get_level()){ob_end_clean();}
error_reporting(E_ALL);
$db = new SQLite3("./userData.db");

#####################################################################################
// The pre-shared key "faskey" (this must be the same as in the openNDS config):
$key="1234567890";
#####################################################################################
if (isset($_SERVER['HTTPS'])) {
	$protocol="https://";
} else {
	$protocol="http://";
}

// Setup some basics:
date_default_timezone_set("UTC");

$username=$password=$gatewayname=$clientip=$gatewayaddress=$hid=$gatewaymac=$clientif=$redir=$client_zone="";

//Parse the querystring

$docroot=$_SERVER['DOCUMENT_ROOT'];
$me=$_SERVER['SCRIPT_NAME'];
$home=str_replace(basename($_SERVER['SCRIPT_NAME']),"",$_SERVER['SCRIPT_NAME']);

//Decode and Parse the querystring

if (isset($_GET['status'])) {
	$redir=$_GET['redir'];
	$redir_r=explode("fas=", $redir);
	$fas=$redir_r[1];
} else if (isset($_GET['fas']))  {
	$fas=$_GET['fas'];
} else {
	exit(0);
}

if (isset($fas)) {
	$decoded=base64_decode($fas);
	$dec_r=explode(", ",$decoded);

	foreach ($dec_r as $dec) {
		@list($name,$value)=explode("=",$dec);
		if ($name == "clientip") {$clientip=$value;}
		if ($name == "clientmac") {$clientmac=$value;}
		if ($name == "gatewayname") {$gatewayname=$value;}
		if ($name == "gatewayurl") {$gatewayurl=rawurldecode($value);}
		if ($name == "version") {$version=$value;}
		if ($name == "hid") {$hid=$value;}
		if ($name == "client_type") {$client_type=$value;}
		if ($name == "gatewayaddress") {$gatewayaddress=$value;}
		if ($name == "gatewaymac") {$gatewaymac=$value;}
		if ($name == "authdir") {$authdir=$value;}
		if ($name == "originurl") {$originurl=$value;}
		if ($name == "clientif") {$clientif=$value;}
		if ($name == "admin_password") {$admin_password=$value;}
		if ($name == "location") {$location=$value;}
	}
}

// Work out the client zone:
$client_zone_r=explode(" ",trim($clientif));

if ( ! isset($client_zone_r[1])) {
	$client_zone="LocalZone:".$client_zone_r[0];
} else {
	$client_zone="MeshZone:".str_replace(":","",$client_zone_r[1]);
}

// Set the path to an image to display. This must be accessible to the client (hint: set up a Walled Garden if you want an Internet based image).
$imagepath="http://$gatewayaddress/images/splash.jpg";

#######################################################
//Start Outputting the requested responsive page:
#######################################################

splash_header();

if (isset($_GET["terms"])) {
	// ToS requested
	display_terms();
	footer();
} elseif (isset($_GET["status"])) {
	// The status page is triggered by a client if already authenticated by openNDS (eg by clicking "back" on their browser)
	status_page();
	footer();
} elseif (isset($_GET["landing"])) {
	// The landing page is served to the client immediately after openNDS authentication, but many CPDs will immediately close
	landing_page();
	footer();
} else {
	login_page();
	footer();
}

// Functions:
function thankyou_page($tok,$username) {
	# Output the "Thankyou page" with a continue button
	# You could include information or advertising on this page
	# Be aware that many devices will close the login browser as soon as
	# the client taps continue, so now is the time to deliver your message.

	$me=$_SERVER['SCRIPT_NAME'];
	$host=$_SERVER['HTTP_HOST'];
	$fas=$GLOBALS["fas"];
	$clientip=$GLOBALS["clientip"];
	$gatewayname=$GLOBALS["gatewayname"];
	$gatewayaddress=$GLOBALS["gatewayaddress"];
	$gatewaymac=$GLOBALS["gatewaymac"];
	$key=$GLOBALS["key"];
	$hid=$GLOBALS["hid"];
	$clientif=$GLOBALS["clientif"];
	$client_zone=$GLOBALS["client_zone"];
	$originurl=$GLOBALS["originurl"];
	$password=$_GET["password"];
	$db=$GLOBALS["db"];
	$authaction="http://$gatewayaddress/opennds_auth/";
	$redir="http://".$host.$me."?fas=$fas&landing=1";
	
	

	/*	You can also send a custom data string to BinAuth. Set the variable $custom to the desired value
		It can contain any information that could be used for post authentication processing
		eg. the values set per client for Time, Data and Data Rate quotas can be sent to BinAuth for a custom script to use
		This string will be b64 encoded before sending to binauth and will appear in the output of ndsctl json
	*/

	$custom=$username;
	echo "	<div class='hidden'>
		<big-red>
			Thankyou!
		</big-red>
		<br>
		<b>Welcome $username</b>
		<br>
		<b>Any Problems</b>
		<a href='https://forms.gle/RswhJ98XwBA3FJP77'><h4>Problems Box</h4></a>
		<med-blue>You are connected to $client_zone</med-blue><br>
		<italic-black>
			Your News or Advertising could be here, contact the owners of this Hotspot to find out how!
		</italic-black>
		<form action=\"".$authaction."\" method=\"get\">
			<input type=\"hidden\" name=\"tok\" value=\"".$tok."\">
			<input type=\"hidden\" name=\"custom\" value=\"$custom\">
			<input type=\"hidden\" name=\"redir\" value=\"".$redir."\"><br>
			<input type=\"submit\" value=\"Continue\" >
		</form>
		<hr>
	</div>
	";

	read_terms();
	flush();
}


function write_log() {
	# In this example we have decided to log all clients who are granted access
	# Note: the web server daemon must have read and write permissions to the folder defined in $logpath
	# By default $logpath is null so the logfile will be written to the folder this script resides in,
	# or the /tmp directory if on the NDS router

	if (file_exists("/etc/config/opennds")) {
		$logpath="/tmp/";
	} elseif (file_exists("/etc/opennds/opennds.conf")) {
		$logpath="/run/";
	} else {
		$logpath="";
	}

	if (!file_exists("$logpath"."ndslog")) {
		mkdir("$logpath"."ndslog", 0700);
	}

	$me=$_SERVER['SCRIPT_NAME'];
	$script=basename($me, '.php');
	$host=$_SERVER['HTTP_HOST'];
	$user_agent=$_SERVER['HTTP_USER_AGENT'];
	$clientip=$GLOBALS["clientip"];
	$clientmac=$GLOBALS["clientmac"];
	$client_type=$GLOBALS["client_type"];
	$gatewayname=$GLOBALS["gatewayname"];
	$gatewayaddress=$GLOBALS["gatewayaddress"];
	$gatewaymac=$GLOBALS["gatewaymac"];
	$clientif=$GLOBALS["clientif"];
	$originurl=$GLOBALS["originurl"];
	$redir=rawurldecode($originurl);
	$username=$_GET["username"];
	$password=$_GET["password"];


	$log=date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']).
		", $script, $gatewayname, $username, $password, $clientip, $clientmac, $client_type, $clientif, $user_agent, $redir\n";

	if ($logpath == "") {
		$logfile="ndslog/ndslog_log.php";

		if (!file_exists($logfile)) {
			@file_put_contents($logfile, "<?php exit(0); ?>\n");
		}
	} else {
		$logfile="$logpath"."ndslog/ndslog.log";
	}

	@file_put_contents($logfile, $log,  FILE_APPEND );
}

function login_page() {
	$username=$password="";
	$me=$_SERVER['SCRIPT_NAME'];
	$host=$_SERVER['HTTP_HOST'];
	$fas=$GLOBALS["fas"];
	$clientip=$GLOBALS["clientip"];
	$gatewayname=$GLOBALS["gatewayname"];
	$gatewayaddress=$GLOBALS["gatewayaddress"];
	$gatewaymac=$GLOBALS["gatewaymac"];
	$key=$GLOBALS["key"];
	$hid=$GLOBALS["hid"];
	$clientif=$GLOBALS["clientif"];
	$client_zone=$GLOBALS["client_zone"];
	$originurl=$GLOBALS["originurl"];
	$db=$GLOBALS["db"];
	$authaction="http://$gatewayaddress/opennds_auth/";
	$redir="http://".$host.$me."?fas=$fas&landing=1";
	
	
	$authaction="http://$gatewayaddress/opennds_auth/";
	if (isset($_GET["username"])) {
		$username=$_GET["username"];
	}
	
	if (isset($_GET["password"])) {
		$password=$_GET["password"];
	}
	if ($username == "" or $password == "") {
		echo "
			<big-red>Welcome!</big-red><br>
			<med-blue>You are connected to $client_zone</med-blue><br>
			<b>Please enter your Username and Pass</b>
			
				<form action=\"".$me."\" method=\"get\">
					<input type=\"hidden\" name=\"fas\" value=\"$fas\">
					<hr>Full Name:<br>
					<input type=\"text\" name=\"username\" value=\"$username\">
					<br>
					password Address:<br>
					<input type=\"password\" name=\"password\" value=\"$password\">
					<br><br>
					<input type=\"submit\" value=\"Accept Terms of Service\">
				</form>
				
				<hr>
			";
	}elseif (!isset($_GET['fas']))  {
			echo "<br><b style=\"color:red;\">Error Goto Login <a href='http://192.168.1.1'>GO to Login</a></b>\n";

		} elseif (validated($username,$password)==true) {
			$custom=$username;
			$tok=hash('sha256', $hid.$key);
			$deauthmac = $db->query('Select clientmac from users where username="'.$username.'";') or die("Error Go Back");
			$oldmac = $deauthmac->fetchArray(SQLITE3_ASSOC);
			$oldmac_client = $oldmac['clientmac'];
			if($oldmac_client != $GLOBALS['clientmac']) {
				$result=exec("/svr/checklocked.sh $oldmac_client");
				if($result == 'locked'){
				echo "<h1>File Error Try Again</h1>";
				echo "<style>
					.hidden {display:none;}
					</style>";
			} 

			}
			thankyou_page($tok,$username);
		} else {
			echo "Invalid Password";
			$_GET['username']="";
			$_GET['password']="";
			login_page();
			read_terms();
			flush();
		}
	}
function status_page() {
	$me=$_SERVER['SCRIPT_NAME'];
	$clientip=$GLOBALS["clientip"];
	$clientmac=$GLOBALS["clientmac"];
	$gatewayname=$GLOBALS["gatewayname"];
	$gatewayaddress=$GLOBALS["gatewayaddress"];
	$gatewaymac=$GLOBALS["gatewaymac"];
	$clientif=$GLOBALS["clientif"];
	$client_zone=$GLOBALS["client_zone"];
	$originurl=$GLOBALS["originurl"];
	$redir=rawurldecode($originurl);

	// Is the client already logged in?
	if ($_GET["status"] == "authenticated") {
		echo "
			<med-blue>You are connected to $client_zone</med-blue><br>
			<p><big-red>You are already logged in and have access to the Internet.</big-red></p>
			<hr>
			<p><italic-black>You can use your Browser, password and other network Apps as you normally would.</italic-black></p>
		";

		read_terms();

		echo "
			<p>
			Your device originally requested <b>$redir</b>
			<br>
			Click or tap Continue to go to there.
			</p>
			<form>
				<input type=\"button\" VALUE=\"Continue\" onClick=\"location.href='".$redir."'\" >
			</form>
		";
	} else {
		echo "
			<p><big-red>ERROR 404 - Page Not Found.</big-red></p>
			<hr>
			<p><italic-black>The requested resource could not be found.</italic-black></p>
		";
	}
	flush();
}

function landing_page() {
	$me=$_SERVER['SCRIPT_NAME'];
	$fas=$_GET["fas"];
	$originurl=$GLOBALS["originurl"];
	$gatewayaddress=$GLOBALS["gatewayaddress"];
	$gatewayname=$GLOBALS["gatewayname"];
	$gatewayurl=$GLOBALS["gatewayurl"];
	$clientif=$GLOBALS["clientif"];
	$client_zone=$GLOBALS["client_zone"];
	$redir=rawurldecode($originurl);

	echo "
		<med-blue>You are connected to $client_zone</med-blue><br>
		<p>
			<big-red>
				You are now logged in and have been granted access to the Internet.
			</big-red>
		</p>
		<hr>
		<p>
			<italic-black>
				You can use your Browser, password and other network Apps as you normally would.
			</italic-black>
		</p>
		<p>
		(Your device originally requested $redir)
		<hr>
		Click or tap Continue to show the status of your account.
		</p>
		<form>
			<input type=\"button\" VALUE=\"Continue\" onClick=\"location.href='".$gatewayurl."'\" >
		</form>
		<hr>
	";

	read_terms();
	flush();
}

function splash_header() {
	$gatewayname=$GLOBALS["gatewayname"];
	$imagepath=$GLOBALS["imagepath"];
	$gatewayname=htmlentities(rawurldecode($gatewayname), ENT_HTML5, "UTF-8", FALSE);

	// Add headers to stop browsers from cacheing 
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	header("Cache-Control: no-cache");
	header("Pragma: no-cache");

	// Output the common header html
	echo "<!DOCTYPE html>\n<html>\n<head>
		<meta charset=\"utf-8\" />
		<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
		<link rel=\"shortcut icon\" href=$imagepath type=\"image/x-icon\">
		<title>$gatewayname</title>
		<style>
	";

	insert_css();

	echo "
		</style>
		</head>
		<body>
		<div class=\"offset\">
		<med-blue>
			$gatewayname
		</med-blue><br>
		<div class=\"insert\">
	";
	flush();
}

function validated($username,$password){
	$db=$GLOBALS['db'];
	$result = $db->query('Select username,password from users where username="'.$username.'" and password="'.$password.'";') or die("Error Go Back");
	if($result->fetchArray(SQLITE3_ASSOC)>0){
		return true;
	}
	return false;
}
function footer() {
	$imagepath=$GLOBALS["imagepath"];
	$version=$GLOBALS["version"];
	$year=date("Y");
	echo "
		<hr>
		<div style=\"font-size:0.5em;\">
			<img style=\"height:60px; width:60px; float:left;\" src=\"$imagepath\" alt=\"Splash Page: For access to the Internet.\">
			&copy; The openNDS Project 2015 - $year<br>
			Portal Version: $version
			<br><br>
		</div>
		</div>
		</div>
		</body>
		</html>
	";
	exit(0);
}

function read_terms() {
	#terms of service button
	$me=$_SERVER['SCRIPT_NAME'];
	$fas=$GLOBALS["fas"];
	echo "
		<form action=\"$me\" method=\"get\">
			<input type=\"hidden\" name=\"fas\" value=\"$fas\">
			<input type=\"hidden\" name=\"terms\" value=\"yes\">
			<input type=\"submit\" value=\"Read Terms of Service\" >
		</form>
	";
}

function display_terms () {
	# This is the all important "Terms of service"
	# Edit this long winded generic version to suit your requirements.
	####
	# WARNING #
	# It is your responsibility to ensure these "Terms of Service" are compliant with the REGULATIONS and LAWS of your Country or State.
	# In most locations, a Privacy Statement is an essential part of the Terms of Service.
	####

	#Privacy
	echo "
		<b style=\"color:red;\">Privacy.</b><br>
		<b>
			By logging in to the system, you grant your permission for this system to store any data you provide for
			the purposes of logging in, along with the networking parameters of your device that the system requires to function.<br>
			All information is stored for your convenience and for the protection of both yourself and us.<br>
			All information collected by this system is stored in a secure manner and is not accessible by third parties.<br>
			In return, we grant you FREE Internet access.
		</b><hr>
	";

	# Terms of Service
	echo "
		<b style=\"color:red;\">Terms of Service for this Hotspot.</b> <br>

		<b>Access is granted on a basis of trust that you will NOT misuse or abuse that access in any way.</b><hr>

		<b>Please scroll down to read the Terms of Service in full or click the Continue button to return to the Acceptance Page</b>

		<form>
			<input type=\"button\" VALUE=\"Continue\" onClick=\"history.go(-1);return true;\">
		</form>
	";

	# Proper Use
	echo "
		<hr>
		<b>Proper Use</b>

		<p>
			This Hotspot provides a wireless network that allows you to connect to the Internet. <br>
			<b>Use of this Internet connection is provided in return for your FULL acceptance of these Terms Of Service.</b>
		</p>

		<p>
			<b>You agree</b> that you are responsible for providing security measures that are suited for your intended use of the Service.
			For example, you shall take full responsibility for taking adequate measures to safeguard your data from loss.
		</p>

		<p>
			While the Hotspot uses commercially reasonable efforts to provide a secure service,
			the effectiveness of those efforts cannot be guaranteed.
		</p>

		<p>
			<b>You may</b> use the technology provided to you by this Hotspot for the sole purpose
			of using the Service as described here.
			You must immediately notify the Owner of any unauthorized use of the Service or any other security breach.<br><br>
			We will give you an IP address each time you access the Hotspot, and it may change.
			<br>
			<b>You shall not</b> program any other IP or MAC address into your device that accesses the Hotspot.
			You may not use the Service for any other reason, including reselling any aspect of the Service.
			Other examples of improper activities include, without limitation:
		</p>

			<ol>
				<li>
					downloading or uploading such large volumes of data that the performance of the Service becomes
					noticeably degraded for other users for a significant period;
				</li>

				<li>
					attempting to break security, access, tamper with or use any unauthorized areas of the Service;
				</li>

				<li>
					removing any copyright, trademark or other proprietary rights notices contained in or on the Service;
				</li>

				<li>
					attempting to collect or maintain any information about other users of the Service
					(including usernames and/or password addresses) or other third parties for unauthorized purposes;
				</li>

				<li>
					logging onto the Service under false or fraudulent pretenses;
				</li>

				<li>
					creating or transmitting unwanted electronic communications such as SPAM or chain letters to other users
					or otherwise interfering with other user's enjoyment of the service;
				</li>

				<li>
					transmitting any viruses, worms, defects, Trojan Horses or other items of a destructive nature; or
				</li>

				<li>
					using the Service for any unlawful, harassing, abusive, criminal or fraudulent purpose.
				</li>
			</ol>
	";

	# Content Disclaimer
	echo "
		<hr>
		<b>Content Disclaimer</b>

		<p>
			The Hotspot Owners do not control and are not responsible for data, content, services, or products
			that are accessed or downloaded through the Service.
			The Owners may, but are not obliged to, block data transmissions to protect the Owner and the Public.
		</p>

		The Owners, their suppliers and their licensors expressly disclaim to the fullest extent permitted by law,
		all express, implied, and statutary warranties, including, without limitation, the warranties of merchantability
		or fitness for a particular purpose.
		<br><br>
		The Owners, their suppliers and their licensors expressly disclaim to the fullest extent permitted by law
		any liability for infringement of proprietory rights and/or infringement of Copyright by any user of the system.
		Login details and device identities may be stored and be used as evidence in a Court of Law against such users.
		<br>
	";

	# Limitation of Liability
	echo "

		<hr><b>Limitation of Liability</b>

		<p>
			Under no circumstances shall the Owners, their suppliers or their licensors be liable to any user or
			any third party on account of that party's use or misuse of or reliance on the Service.
		</p>

		<hr><b>Changes to Terms of Service and Termination</b>

		<p>
			We may modify or terminate the Service and these Terms of Service and any accompanying policies,
			for any reason, and without notice, including the right to terminate with or without notice,
			without liability to you, any user or any third party. Please review these Terms of Service
			from time to time so that you will be apprised of any changes.
		</p>

		<p>
			We reserve the right to terminate your use of the Service, for any reason, and without notice.
			Upon any such termination, any and all rights granted to you by this Hotspot Owner shall terminate.
		</p>
	";

	# Inemnity
	echo "
		<hr><b>Indemnity</b>

		<p>
			<b>You agree</b> to hold harmless and indemnify the Owners of this Hotspot,
			their suppliers and licensors from and against any third party claim arising from
			or in any way related to your use of the Service, including any liability or expense arising from all claims,
			losses, damages (actual and consequential), suits, judgments, litigation costs and legal fees, of every kind and nature.
		</p>

		<hr>
		<form>
			<input type=\"button\" VALUE=\"Continue\" onClick=\"history.go(-1);return true;\">
		</form>
	";
	flush();
}

function insert_css() {
	echo "
	body {
		background-color: lightgrey;
		color: #140f07;
		margin: 0;
		padding: 10px;
		font-family: sans-serif;
	}

	hr {
		display:block;
		margin-top:0.5em;
		margin-bottom:0.5em;
		margin-left:auto;
		margin-right:auto;
		border-style:inset;
		border-width:5px;
	}

	.offset {
		background: rgba(300, 300, 300, 0.6);
		border-radius: 10px;
		margin-left:auto;
		margin-right:auto;
		max-width:600px;
		min-width:200px;
		padding: 5px;
	}

	.insert {
		background: rgba(350, 350, 350, 0.7);
		border: 2px solid #aaa;
		border-radius: 10px;
		min-width:200px;
		max-width:100%;
		padding: 5px;
	}

	.insert > h1 {
		font-size: medium;
		margin: 0 0 15px;
	}

	img {
		width: 40%;
		max-width: 180px;
		margin-left: 0%;
		margin-right: 10px;
		border-radius: 3px;
	}

	input[type=text], input[type=password], input[type=password], input[type=number], input[type=tel] {
		font-size: 1em;
		line-height: 2em;
		height: 2em;
		color: #0c232a;
		background: lightgrey;
	}

	input[type=submit], input[type=button] {
			font-size: 1em;
		line-height: 2em;
		height: 2em;
		font-weight: bold;
		border: 0;
		border-radius: 10px;
		background-color: #1a7856;
		padding: 0 10px;
		color: #fff;
		cursor: pointer;
		box-shadow: rgba(50, 50, 93, 0.1) 0 0 0 1px inset,
		rgba(50, 50, 93, 0.1) 0 2px 5px 0, rgba(0, 0, 0, 0.07) 0 1px 1px 0;
	}

	med-blue {
		font-size: 1.2em;
		color: #0073ff;
		font-weight: bold;
		font-style: normal;
	}

	big-red {
		font-size: 1.5em;
		color: #c20801;
		font-weight: bold;
	}

	italic-black {
		font-size: 1em;
		color: #0c232a;
		font-weight: bold;
		font-style: italic;
		margin-bottom: 10px;
	}

	copy-right {
		font-size: 0.7em;
		color: darkgrey;
		font-weight: bold;
		font-style: italic;
	}

	";
	flush();
}

?>