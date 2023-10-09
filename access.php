<?php

/*
** access.php - this program will perform two different actions, depending on how it is
** 	invoked.  The first (most important) action is the following:
** 1) this program will log everytime it is invoked with the "application" argument. BUT
** more than that, it will log to a specific file dictated by whatever "application" invokes
** this program.  This can then be used by any HTML page to log access to that page. For
** example, in the HTML page for the OW Challenge we would add the following:
**
**		$(document).ready(function() {
**			$.post( "../Access/access.php", { 
**				application : "OWChallenge",
**				} );	
**		});
**
** where the 'application' tag is assigned the name of the application. This will log access
** to the application's access log file.
** It's assumed that the applications log file exists in the points/Access/{app}AccessLogs 
** directory, where {app} is the 'application' tag ('OWChallenge' in the example above.)
** The log file will be named {app}Log.txt ('OWChallengeLog.txt' in the above example) and
** is formatted as a CSV file.
**
** The second (and quite useful) action is the following:
** 2) this program will serve an HTML page everytime it is invoked with the "display=x" query 
**	string, where "x" is one of the following:
**		- s : summary. Displays a summary count for every application
**		- ? : help text
**		- (anything else) : This page will list all "applications" it knows about, 
**			and details on how often that application is invoked.
**
** SPECIAL CASE: if this program is invoked with neither "application" nor "display" then it
**	will act as though it was invoked with "application" of "Unknown" (and log that.)
*/

// did we get invoked with "application", "display", or nothing?
$post = file_get_contents( "php://input" );
$gotInvalidPost = 0;		// assume this is a valid post
$matchAppl = $matchDisp = 0;

$matchAppl = preg_match( '/^application=([^\&]+)(\&|$)/', $post, $matches );
if( $matchAppl ) {
	// invoked with "application=..."
	$appName = $matches[1];
} else {
	$matchDisp = $_GET['display'];
}

if( !$matchAppl && !$matchDisp ) {
	// either nothing or crap passed - assume "Unknown"
	$matchAppl = 1;
	$appName = "Unknown";
	$gotInvalidPost = 1;
}

if( $matchAppl ) {
	// we're going to log this hit...
	if( ! isset( $appName ) ) {
		// handle weirdness... we got a hit but don't know where it came from. Log it anyway...
		$appName = "Unknown";
		$gotInvalidPost = 1;
	}
	$dirName = $appName . "AccessLogs";
	mkdir( $dirName, 0740, true );
	$accessLog = fopen( "$dirName/$appName" . "Log.txt", "a" );
	date_default_timezone_set( "America/New_York" );
	$dateTime = date( "D d M Y - g:i:sA T" );
	fwrite( $accessLog, $dateTime . "," );
	if( $gotInvalidPost ) {
		// help us figure out how we got the invalid post:
		fwrite( $accessLog, "post is: '$post'," );
	}
	//		fwrite( $accessLog, "app2=$app2,");
	$hdrs = GetHeaderList();
	foreach( $hdrs as $hdrName => $hdrValue ) {
		fwrite( $accessLog, "$hdrName:\"$hdrValue\"," );
	}
	fwrite( $accessLog, "\n" );
} else {
	// we are being asked to display something
	if( strcmp( $matchDisp, "?" ) == 0 ) {
		DumpHelp( $fhandle );
	} else {
		// we are being asked to display the logs we've collected
		GenerateHTMLHead();
		$dirs = GetListOfDirs();
	
		foreach( $dirs as $dir ) {
			print "<h1>$dir</h1>\n<div style=\"margin-left:20px;\">\n";
			// open this directory to get the access log file:
			$subDir = dir( $dir );
			while (false !== ($entry = $subDir->read())) {
				if ($entry != '.' && $entry != '..') {
					$theFile = $dir . "/" . $entry;
					if (is_file($theFile)) {
						// we have an access log file:
						$fhandle = fopen( $theFile, "r" );
						if( $fhandle ) {
							// what we do with this access log file depends on what was requested:
							if( strcmp( $matchDisp, "s" ) == 0 ) {
								DumpAccessSummary( $fhandle );
							} else {
								DumpRawAccess( $fhandle );
							}
						}
						fclose( $fhandle );
						print "</div>\n";
					}
				}
			}
		} //end of foreach( ....
	}
	
	GenerateHTMLTail();
	
}


function DumpAccessSummary( $fhandle ) {
	$numLast7Days = 0;
	$numLast30Days = 0;
	$numThisMonth = 0;
	$numThisYear = 0;
	$numTotal = 0;
	
	$numBotLast7Days = 0;
	$numBotLast30Days = 0;
	$numBotThisMonth = 0;
	$numBotThisYear = 0;
	$numBotTotal = 0;
	
	$numTestingLast7Days = 0;
	$numTestingLast30Days = 0;
	$numTestingThisMonth = 0;
	$numTestingThisYear = 0;
	$numTestingTotal = 0;
	
	$dateOfFirstLog = "(?)";
	$todaysDate = new DateTime();
	$thisMonth = $todaysDate->format( 'M' );
	$thisYear = $todaysDate->format( 'Y' );
	//print "<br>Access: today's Year: '$thisYear', today's month: '$thisMonth'</br>\n";
	while( ($buffer = fgets( $fhandle, 4096 )) ) {
		$numTotal++;
		$buffer = str_replace( array( "\n\r", "\n", "\r" ), "", $buffer );
		$thisIsABot = 0;
		$thisIsATest = 0;
		if( stripos( $buffer, "bot" ) !== false ) {
			$thisIsABot = 1;
			$numBotTotal++;
		} elseif( stripos( $buffer, "bup" ) !== false ) {
			$thisIsATest = 1;
			$numTestingTotal++;
		}
		$logDateStr = substr( $buffer, 4,11 );
		if( $numTotal == 1 ) {
			$dateOfFirstLog = $logDateStr;
		}
		$theMonth = substr( $logDateStr, 3, 3 );
		$theYear = substr( $logDateStr, 7, 4 );
		$logDate = DateTime::createFromFormat( 'd M Y', $logDateStr );
		$interval = $logDate->diff( $todaysDate );
		$numDays = $interval->days;
		if( $numDays < 8 ) {
			$numLast7Days++;
			if( $thisIsABot ) {
				$numBotLast7Days++;
			} elseif( $thisIsATest ) {
				$numTestingLast7Days++;
			}
		}
		if( $numDays < 31 ) {
			$numLast30Days++;
			if( $thisIsABot ) {
				$numBotLast30Days++;
			} elseif( $thisIsATest ) {
				$numTestingLast30Days++;
			}
		}
		if( $thisYear == $theYear ) {
			$numThisYear++;
			if( $thisIsABot ) {
				$numBotThisYear++;
			} elseif( $thisIsATest ) {
				$numTestingThisYear++;
			}
			if( (strcasecmp( $thisMonth, $theMonth ) == 0)  ) {
				$numThisMonth++;
				if( $thisIsABot ) {
					$numBotThisMonth++;
				} elseif( $thisIsATest ) {
					$numTestingThisMonth++;
				}
			}
		}
		//print "<br>buffer: '$buffer', Log: '$logDateStr'; log's month: '$theMonth', log's year: '$theYear'</br>\n";
	
	
	}
	print "<br>Number of total requests since $dateOfFirstLog: $numTotal";
	if( $numTotal ) {
		if( $numBotTotal > 0 ) {
			$diff = $numTotal-$numBotTotal;
			print " (Of which $numBotTotal were probably bots, thus " . $diff .
				" were probably human.)";
		} else {
			print " (No bots detected.)";
		}
		if( $numTestingTotal > 0 ) {
			print " (However, $numTestingTotal were probably testing)";
		} else {
			print " (No testing detected.)";
		}
	}
	print "\n";
	
	print "<br>Number of requests within the last 7 days: $numLast7Days";
	if( $numLast7Days ) {
		if( $numBotLast7Days > 0 ) {
			$diff = $numLast7Days-$numBotLast7Days;
			print " (Of which $numBotLast7Days were probably bots, thus " . $diff .
				" were probably human.)";
		} else {
			print " (No bots detected.)";
		}
		if( $numTestingLast7Days > 0 ) {
			print " (However, $numTestingLast7Days were probably testing.)";
		} else {
			print " (No testing detected.)";
		}
	}
	print "\n";

	print "<br>Number of requests within the last 30 days: $numLast30Days";
	if( $numLast30Days ) {
		if( $numBotLast30Days > 0 ) {
			$diff = $numLast30Days-$numBotLast30Days;
			print " (Of which $numBotLast30Days were probably bots, thus " . $diff .
				" were probably human.)";
		} else {
			print " (No bots detected.)";
		}
		if( $numTestingLast30Days > 0 ) {
			print " (However, $numTestingLast30Days were probably testing.)";
		} else {
			print " (No testing detected.)";
		}
	}
	print "\n";

	print "<br>Number of requests this month: $numThisMonth";
	if( $numThisMonth ) {
		if( $numBotThisMonth > 0 ) {
			$diff = $numThisMonth-$numBotThisMonth;
			print " (Of which $numBotThisMonth were probably bots, thus " . $diff .
				" were probably human.)";
		} else {
			print " (No bots detected.)";
		}
		if( $numTestingThisMonth > 0 ) {
			print " (However, $numTestingThisMonth were probably testing.)";
		} else {
			print " (No testing detected.)";
		}
	}
	print "\n";

	print "<br>Number of requests this year: $numThisYear";
	if( $numThisYear ) {
		if( $numBotThisYear > 0 ) {
			$diff = $numThisYear-$numBotThisYear;
			print " (Of which $numBotThisYear were probably bots, thus " . $diff .
				" were probably human.)";
		} else {
			print " (No bots detected.)";
		}
		if( $numTestingThisYear > 0 ) {
			print " (However, $numTestingThisYear were probably testing.)";
		} else {
			print " (No testing detected.)";
		}
	}
	print "\n";




} // end of DumpAccessSummary()




function DumpRawAccess( $fhandle ) {
	while( ($buffer = fgets( $fhandle, 4096 )) ) {
		$buffer = str_replace( array( "\n\r", "\n", "\r" ), "", $buffer );
		print "<br>$buffer</br>\n";
	}
} // end of DumpRawAccess()



function DumpHelp( $fhandle ) {
	print "<h1>Access Counts for Tracked Applications</h1>\n";
	print "<p><a href=\"https://pacmdev.org/points/Access/access.php?display=x\">\n";
	print "https://pacmdev.org/points/Access/access.php?display=x</a>\n";
	print "<br> --- or ---</br>\n";
	print "<a href=\"https://data.pacificmasters.org/points/Access/access.php?display=x\">\n";
	print "https://data.pacificmasters.org/points/Access/access.php?display=x</a>\n";
	print "<p>where \"x\" is one of the following:<br>";
	print "<ul>\n";
	print "    <li>s : summary. Displays a summary count for every application</li>\n";
	print "    <li>? : this help page</li>\n";
	print "    <li>(anything else) : list all \"applications\" it knows about, " .
			"and details on how often that application is invoked.</li>\n";
	print "</ul>\n";


} // end of DumpHelp()




function GetListOfDirs() {
	$dirs = array();

	// directory handle
	$dir = dir(".");

	while (false !== ($entry = $dir->read())) {
		if ($entry != '.' && $entry != '..') {
		   if (is_dir($entry)) {
				$dirs[] = $entry; 
		   }
		}
	}
	return $dirs;
} // end of GetListOfDirs()

function GenerateHTMLHead( ) {
	?>
	<!DOCTYPE html>
	<html lang="en" class="no-js">
	<head>
		<meta charset="utf-8">
		<title>Access to our Applications on <?php echo $_SERVER['SERVER_NAME'] ?></title>
	</head>
	<body>
	<center>
	Access to our Applications on <?php echo $_SERVER['SERVER_NAME'] ?>
	</center>
	<p>
	<?php
} // end of US_GeneratePageHead()


function GenerateHTMLTail() {
	?>
	</body>
	</html>
	<?php
} // end of GenerateHTMLTail()








/*
 * GetHeaderList - get a list of headers sent along with a client request to us.
 *
 */
function GetHeaderList() {
    //create an array to put our header info into.
    $headerList = array();
    if( 1 ) {
		//loop through the $_SERVER superglobals array.
		foreach ($_SERVER as $name => $value) {
			//if the name starts with HTTP_, it's a request header.
			if (preg_match('/^HTTP_/',$name)) {
				//convert HTTP_HEADER_NAME to the typical "Header-Name" format.
				//$name = strtr(substr($name,5), '_', ' ');
				//$name = ucwords(strtolower($name));
				//$name = strtr($name, ' ', '-');
				//Add the header to our array.
				$headerList[$name] = $value;
			}
		}
	} else {
		// just get one header: 
	    $headerList['HTTP_USER_AGENT'] = $_SERVER{'HTTP_USER_AGENT'};
	}
    //Return the array.
    return $headerList;
} // end of GetHeaderList()

?>