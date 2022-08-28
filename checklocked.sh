#!/bin/sh

macaddr=$1

do_ndsctl () {
	local timeout=4

	for tic in $(seq $timeout); do
		ndsstatus="ready"
		ndsctlout=$(eval ndsctl "$ndsctlcmd")

		for keyword in $ndsctlout; do

			if [ $keyword = "locked" ]; then
				ndsstatus="busy"
				sleep 1
				break
			fi
		done
		
		if [ "$ndsstatus" = "ready" ]; then
			break
		fi
	done
	echo $ndsstatus
}

ndsctlcmd='deauth'
do_ndsctl
