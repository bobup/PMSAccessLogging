#!/bin/bash

# ProdPush.bash - push the PMSAccessLogging access.php file from dev to production
#
# This script assumes that this host can talk to production using its public key.  If that isn't
# true then the user of this script will have to supply the password to production 3 times!!!
#
# PASSED:
#   n/a


STARTDATE=`date +'%a, %b %d %G at %l:%M:%S %p %Z'`
SIMPLE_SCRIPT_NAME=`basename $0`
EMAIL_NOTICE=bobup@acm.org
TARBALL=access_`date +'%d%b%Y'`.zip

PRODDIRECTORY=/usr/home/pacmasters/public_html/pacificmasters.org/sites/default/files/comp/points/Access
USERHOST=$USER@`hostname`

# Get to work!

echo ""; echo '******************** Begin' "$0"

#
# LogMessage - generate a log message to various devices:  email, stdout, and a script
#   log file.
#
# PASSED:
#   $1 - the subject of the log message.
#   $2 - the log message
#
LogMessage() {
    echo "$2"
	/usr/sbin/sendmail -f $EMAIL_NOTICE $EMAIL_NOTICE <<- BUpLM
		Subject: $1
		$2
		BUpLM
} # end of LogMessage()

cd /usr/home/pacdev/public_html/pacmdev.org/sites/default/files/comp/points/Access > /dev/null
tar czf $TARBALL access.php
# push tarball to production
scp -p $TARBALL pacmasters@pacmasters.pairserver.com:$PRODDIRECTORY
# untar the new access.php
# Also clean out old tar files.
ssh pacmasters@pacmasters.pairserver.com \
	"( cd $PRODDIRECTORY; rm -f access.php; tar xf $TARBALL; rm -f $TARBALL )"

LogMessage "OW PMSAccessLogging access.php pushed to PRODUCTION by $SIMPLE_SCRIPT_NAME on $USERHOST" \
	"$(cat <<- BUp9
	Destination File: $PRODDIRECTORY/access.php?display=x
	Destination URL: https://data.pacificmasters.org/points/Access/access.php?display=x
	(STARTed on $STARTDATE, FINISHed on $(date +'%a, %b %d %G at %l:%M:%S %p %Z'))
	BUp9
	)"

echo 'Done!'

