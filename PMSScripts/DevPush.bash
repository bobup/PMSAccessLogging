#!/bin/bash


# DevPush.bash - this script is intended to be executed on the PMS Dev machine ONLY.  
#   It will push the PMSAccessLogging access.php file to the Dev Access page, e.g.:
#			http://www.pacmdev.org/points/Access/access.php
#
# PASSED:
#	n/a
#
#	This script is assumed to be located in the PMSAccessLogging PMSScripts directory.
#

STARTDATE=`date +'%a, %b %d %G at %l:%M:%S %p %Z'`
EMAIL_NOTICE=bobup@acm.org
SIMPLE_SCRIPT_NAME=`basename $0`
DESTINATION_DIR=/usr/home/pacdev/public_html/pacmdev.org/sites/default/files/comp/points/Access
USERHOST=$USER" at "`hostname`


#
# LogMessage - generate a log message to various devices:  email, stdout, and a script 
#	log file.
#
# PASSED:
#	$1 - the subject of the log message.
#	$2 - the log message
#
LogMessage() {
	echo "$2"
	/usr/sbin/sendmail -f $EMAIL_NOTICE $EMAIL_NOTICE <<- BUpLM
		Subject: $1
		$2
		BUpLM
} # end of LogMessage()

##########################################################################################


# Get to work!

echo ""; echo '******************** Begin' "$0"

# compute the full path name of the directory holding this script.  We'll find the
# Generated files directory relative to this directory:
script_dir=$(dirname $0)
# Next compute the full path name of the directory holding access.php
pushd $script_dir/.. >/dev/null; 
GENERATED_DIR=`pwd -P`
# do we have the generated files that we want to push?
if [ -e "access.php" ] ; then
	# yes!  get to work:
	mkdir -p $DESTINATION_DIR
    rm -f $DESTINATION_DIR/access.php
	cp  access.php  $DESTINATION_DIR
	cp  user.ini  $DESTINATION_DIR/.user.ini
	LogMessage "access.php pushed to dev by $SIMPLE_SCRIPT_NAME on $USERHOST" "$(cat <<- BUp9 
		Destination File: $DESTINATION_DIR/access.php?display=x
		(STARTed on $STARTDATE, FINISHed on $(date +'%a, %b %d %G at %l:%M:%S %p %Z'))
		BUp9
		)"
fi

popd >/dev/null
echo ""; echo '******************** End of ' "$0"

exit;
