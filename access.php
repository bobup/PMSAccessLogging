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
**	string, and possibly other query arguments.  See the DumpHelp() function below.
**
** SPECIAL CASE #1: if this program is invoked with neither "application" nor "display" then it
**	will act as though it was invoked with "application : Unknown" and, following action #1 
**	above, log that. This will catch bots.
**
** SPECIAL CASE #2: if the full querystring is '?' it will act as though "display=?" was passed.
**
** NOTE: to see errors when running in the CLI use php -d display_errors=1 -d error_reporting=E_ALL
*/
date_default_timezone_set( "America/New_York" );
$dateTime = date( "D d M Y - g:i:sA T" );

// did we get invoked with "application", "display", or nothing?
$post = file_get_contents( "php://input" );
$gotInvalidPost = 0;		// assume this is a valid post

// list of months
$monthsArray = ['ignored', 'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct',
	'nov', 'dec' ];

// this program was either:
//	- invoked with the "application=..." post, or
//	- invoked with 0 or more query or cli arguments.
$matchAppl = 0;		// set to non-empty / non-zero if "application=..." posted
$matchAppl = preg_match( '/^application=([^\&]+)(\&|$)/', $post, $matches );
$appName = "";		// we get the application name below if it was invoked with one

// get query or cli arguments:
$args = getArguments();
// handle possible arguments:
$matchDisp = 0;		// set to non-empty / non-zero if "?display=..." supplied via query string
$logsDir = 0;		// set to non-empty / non-zero if we are to use the logs from somewhere other than '.'
$monthsCovered = 0;	// set to non-empty / non-zero if we only report on logs with the given date range.
$minMonth = $maxMonth = 0;	// set based on the value of $monthsCovered.  1-12 if valid

// let's get down to business...
if( $matchAppl ) {
	// invoked with "application=..."
	$appName = $matches[1];
} else if( array_key_exists( "?", $args) ) {
	// Special case #2: 
	$matchDisp = "?";
} else {
	if( array_key_exists( "display", $args ) ) {
		$matchDisp = $args['display'];
	}
	if( array_key_exists( 'ldir', $args ) ) {
		$logsDir = $args['ldir'];		// only for display of logs - not writing them!
	}
	if( array_key_exists( 'm', $args ) ) {
		$monthsCovered = $args['m'];	// m=aaa-bbb
		if( ! preg_match( '/^(...)-(...)$/', $monthsCovered, $matches ) ) {
			GenerateHTMLHead();
			print "Parsing month range failed ('$monthsCovered'). Abort!\n";
			GenerateHTMLTail();
			exit();
		} else {
			$minMonth = MonthToNumber( strtolower( $matches[1] ) );
			$maxMonth = MonthToNumber( strtolower( $matches[2] ) );
			if( ! ($minMonth && $maxMonth) ) {
				GenerateHTMLHead();
				print "Invalid months passed ('$monthsCovered'). Abort!\n";
				GenerateHTMLTail();
				exit();
			}
		}
	}
}

if( !$matchAppl && !$matchDisp ) {
	// either nothing or crap passed - assume "Unknown"
	$matchAppl = 1;
	$appName = "Unknown";
	$gotInvalidPost = 1;
}

//print "appName=$appName, matchDisp=$matchDisp, logsDir=$logsDir, monthsCovered=$monthsCovered\n";

if( $matchAppl ) {
	// we're going to log this hit...
	if( ! isset( $appName ) ) {
		// handle weirdness... we got a hit but don't know where it came from. Log it anyway...
		$appName = "Unknown";
		$gotInvalidPost = 1;
	}
	$dirName = $appName . "AccessLogs";
	if( !is_dir( $dirName ) ) {
		mkdir( $dirName, 0740, true );
	}
	$accessLog = fopen( "$dirName/$appName" . "Log.txt", "a" );
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
	GenerateHTMLHead();
	if( strcmp( $matchDisp, "?" ) == 0 ) {
		DumpHelp( );
	} else {
		// we are being asked to display the logs we've collected
		if( $logsDir ) {
			// we're processing a custom log directory.
			if( !chdir( $logsDir ) ) {
				print "Changing directory to '$logsDir' failed. Abort!\n";
				GenerateHTMLTail();
				exit();
			}
		}
		$dirs = GetListOfDirs();
		
		foreach( $dirs as $dir ) {
			print "<h1>$dir</h1>\n<div style=\"margin-left:20px;\">\n";
			// open this directory to get the access log file:
			$subDir = dir( $dir );
			while (false !== ($entry = $subDir->read())) {
				if ($entry != '.' && $entry != '..' ) {
					// this is a real file and not '.' or '..'.
					$theFile = $dir . "/" . $entry;
					if (is_file($theFile)) {
						// we have an access log file:
						$fhandle = fopen( $theFile, "r" );
						if( $fhandle ) {
							// what we do with this access log file depends on what was requested:
							if( strcmp( $matchDisp, "s" ) == 0 ) {
								DumpAccessSummary( $fhandle, $minMonth, $maxMonth );
							} else if( strcmp( $matchDisp, "a" ) == 0 ) {
								DumpTotalAccess( $fhandle, $minMonth, $maxMonth );
							} else {
								DumpRawAccess( $fhandle, $minMonth, $maxMonth );
							}
						}
						fclose( $fhandle );
					}
				}
			}
			print "</div>\n";

		} //end of foreach( ....
	}
	
	GenerateHTMLTail();
	
}


/*
 * getArguments - get CLI arguments or GET parameters from a browser.
 *
 * RETURNED:
 *	an array of name/value pairs , e.g.
 *		display => a
 *		ldir => xxx
 *
 */
function getArguments(): array {
    if (php_sapi_name() === 'cli') {
        global $argv;
        $args=[];
        foreach (array_slice($argv, 1) as $arg) {
            // Format: -key=value
            if (strpos($arg, '-') === 0) {
                $pair = explode('=', substr($arg, 1), 2);
                $args[$pair[0]] = $pair[1] ?? true; // if no value, treat as flag
            } elseif( strpos($arg, '=') > 0 ) {
            	// format: key=value
            	$pair = explode( '=', $arg );
                $args[$pair[0]] = $pair[1] ?? true; // if no value, treat as flag
            } else {
            	// Positional argument
                $args[$arg] = "";
            }
        }
        return $args;        
    } else {
        return $_REQUEST;
    }
} // end of getArguments()









/*
 * MonthToNumber - convert a month short name to a number.
 *
 * PASSED:
 *	$monthName - one of the 12 month short names, e.g. jan, feb, mar, ...
 *		Must be lower case.
 *
 * RETURNED:
 *	$result - the corresponding number of the month, jan is 1, feb is 2, etc.
 */
function MonthToNumber( $monthName ) {
	global $monthsArray;
	$result = array_search( $monthName, $monthsArray );
	return $result;
} // end of MonthToNumber()




/*
 * DumpTotalAccess - dump the passed log file when passed "display=a"
 *
 * PASSED:
 *	$fhandle - the file handle for the log file (read only)
 *	$minMonth - if non-zero, dump only those months between $minMonth and $maxMonth, inclusive.
 *		If non-zero it is a number 1-12.
 *	$maxMonth - see $minMonth above.
 *
 * RETURNED:
 *	n/a
 *
 * NOTES:
 *	This function will write HTML to its output stream.
 *
 */
function DumpTotalAccess( $fhandle, $minMonth, $maxMonth ) {
	global $monthsArray;
	$numTotal = 0;
	$numBotTotal = 0;
	$numTestingTotal = 0;
	$dateOfFirstLog = "";
	$dateOfLastLog = "";
	$monthNum = 0;
	
	while( ($buffer = fgets( $fhandle, 4096 )) ) {
		// if we only care about a specific date range then check it now.
		if( $minMonth ) {
			// get the month for this log entry:
			if( preg_match( '/^.......(...)/', $buffer, $matches ) ) {
				$monthName = $matches[1];
				$monthNum = MonthToNumber( strtolower( $monthName ) );
			} else {
				GenerateHTMLHead();
				print "Unable to parse out month from log entry ('$buffer'). Abort!\n";
				GenerateHTMLTail();
				exit();
			}
			// does this log entry lie within the month range we're interested in?
			if( ($monthNum < $minMonth) || ($monthNum > $maxMonth) ) {
				// Nope!  skip this one:
				continue;
			}
		}
	
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
		// the first field we are processing is the date/time field which looks like this:
		//    Sat 12 Aug 2023 - 7:41:34PM EDT
		
		$logDateStr = substr( $buffer, 4,11 );
		if( $numTotal == 1 ) {
			$dateOfFirstLog = $logDateStr;		// from 'Sun 31 Aug 2025' we get this: '31 Aug 2025'
		}
		$dateOfLastLog = $logDateStr;		// as far as we know this is the last log...
	} // end of while...

	if( $dateOfFirstLog ) {
		$numRealTotal = $numTotal-$numBotTotal-$numTestingTotal;
		$numRealTotalStr = "";
		if( $numRealTotal != $numTotal ) {
			$numRealTotalStr = " ($numRealTotal actual requests, $numBotTotal bots, $numTestingTotal tests)";
		}
		$considerMonths = "";
		if( $minMonth ) {
			$considerMonths = " (considering the months $monthsArray[$minMonth]-$monthsArray[$maxMonth] only)";
		}
		print "<br>Number of total requests between $dateOfFirstLog and $dateOfLastLog$considerMonths: $numTotal$numRealTotalStr<br>\n";
	
		// get the interval (in days) we're looking at:
		$numDays = 0;
		if( $minMonth ) {
			// we're only looking at subset of months of each year, so this is a bit complicated:
			preg_match( '/^.......(....)/', $dateOfFirstLog, $matches );
			$minYear = $matches[1];
			preg_match( '/^.......(....)/', $dateOfLastLog, $matches );
			$maxYear = $matches[1];
			for( $year = $minYear; $year <= $maxYear; $year++ ) {
				for( $month = $minMonth; $month <= $maxMonth; $month++ ) {
					$numDays += NumDaysInMonth( $month, $year );
				}
			}
		} else {
			// we just need the number of days between the first and last logs.
			// Two dates in "dd MMM yyyy" format
			$date1 = DateTime::createFromFormat('d M Y', $dateOfFirstLog );
			$date2 = DateTime::createFromFormat('d M Y', $dateOfLastLog );
			// Find the difference
			$interval = $date1->diff($date2);
			// Get the number of days between
			$numDays = $interval->days;
		}
		$numRealTotalPerDay = number_format( $numRealTotal / $numDays, 1 );
	
		print "This interval we are considering is " . $numDays . 
			" days, which works out to roughly $numRealTotalPerDay actual requests per day<br>\n";
	} else {
		$monthsSpecified = "";
		if( $minMonth ) {
			$monthsSpecified = " during the months specified";
		}
		print "<br>There were no requests made to this application$monthsSpecified<br>\n";
	}
	
	print "<p>&nbsp;&nbsp;\n";
	
} // end of DumpTotalAccess()



// 				$numDays += NumDaysInMonth( $month, $year );
/*
 * days_in_month($month, $year)
 * Returns the number of days in a given month and year, taking into account leap years.
 *
 * $month: numeric month (integers 1-12)
 * $year: numeric year (any integer)
 *
 * Prec: $month is an integer between 1 and 12, inclusive, and $year is an integer.
 * Post: none
 */
// corrected by ben at sparkyb dot net
function NumDaysInMonth( $month, $year ) {
	$result = $month == 2 ? ($year % 4 ? 28 : ($year % 100 ? 29 : ($year % 400 ? 28 : 29))) : 
		(($month - 1) % 7 % 2 ? 30 : 31);
	return $result;
} // end of NumDaysInMonth()


/*
 * DumpAccessSummary -  dump the passed log file when passed "display=s"
 *
 * PASSED:
 *	$fhandle - the file handle for the log file (read only)
 *	$minMonth - if non-zero, dump only those months between $minMonth and $maxMonth, inclusive.
 *		If non-zero it is a number 1-12.
 *	$maxMonth - see $minMonth above.
 *
 * RETURNED:
 *	n/a
 *
 * NOTES:
 *	This function will write HTML to its output stream.
 *
 */
function DumpAccessSummary( $fhandle, $minMonth, $maxMonth ) {
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
		// the first field we are processing is the date/time field which looks like this:
		//    Sat 12 Aug 2023 - 7:41:34PM EDT
		
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



/*
 * DumpRawAccess -  dump the passed log file when passed "display={any unrecognized value}"
 *
 * PASSED:
 *	$fhandle - the file handle for the log file (read only)
 *	$minMonth - if non-zero, dump only those months between $minMonth and $maxMonth, inclusive.
 *		If non-zero it is a number 1-12.
 *	$maxMonth - see $minMonth above.
 *
 * RETURNED:
 *	n/a
 *
 * NOTES:
 *	This function will write HTML to its output stream.
 *
 */
function DumpRawAccess( $fhandle, $minMonth, $maxMonth ) {
	while( ($buffer = fgets( $fhandle, 4096 )) ) {
		$buffer = str_replace( array( "\n\r", "\n", "\r" ), "", $buffer );
		print "<br>$buffer</br>\n";
	}
} // end of DumpRawAccess()



/*
 * DumpHelp - explain this program. Writes to stdout in CLI mode, or the web browser in web mode.
 *
 */
function DumpHelp( ) {
	print "<h1>Access Counts for Tracked Applications</h1>\n";
	print "<p><a href=\"https://pacmdev.org/points/Access/access.php?display=x\">\n";
	print "https://pacmdev.org/points/Access/access.php?display=x[&ldir=xxx][&m=aaa-bbb]</a>\n";
	print "<br> --- or ---</br>\n";
	print "<a href=\"https://data.pacificmasters.org/points/Access/access.php?display=x\">\n";
	print "https://data.pacificmasters.org/points/Access/access.php?display=x[&ldir=xxx][&m=aaa-bbb]</a>\n";
	print "<p>where \"x\" is one of the following:<br>";
	print "<ul>\n";
	print "    <li>s : summary. Displays a summary count for every application</li>\n";
	print "    <li>a : all hits. Displays a total count for every application</li>\n";
	print "    <li>? : this help page</li>\n";
	print "    <li>(anything else) : list all \"applications\" it knows about, " .
			"and details on how often that application is invoked.</li>\n";
	print "</ul>\n";
	
	print "<p>The 'ldir=xxx' is optional. If not supplied the default directory of log files is used.\n";
	print "If the ldir argument is passed, the 'xxx' is a directory, relative to the default directory, \n";
	print "that is used to find the log files to process.</p>\n";
	
	print "<p>The 'm=aaa-bbb' is optional. If not supplied then all months of the year are processed for all log files.\n";
	print "If the m argument is passed, then aaa and bbb are months represented by the first three letters of their full\n";
	print "names. In that case only those months within their range are used. E.g. '-m=jan-mar' means only process those \n";
	print "log files logged within the dates January through March for every year.\n</p>";

} // end of DumpHelp()



/*
 * GetListOfDirs - get a list of all directories in the current directory whose name ends with "Logs"
 *
 * RETURN:
 *	$dirs - an array of directory names. 
 *
 * NOTES: if the directory "UnknownAccessLogs" exists then it will always be last in the list.
 */
function GetListOfDirs() {
	$dirs = array();
	$UnknownDirSeen = 0;
	$UnknownDirName = "UnknownAccessLogs";

	// directory handle
	$dir = dir(".");

	while (false !== ($entry = $dir->read())) {
		// we only care about files whose name ends in 'Logs'.
		if( preg_match( '/Logs$/', $entry ) ) {
			// this is a real directory we're interested in. However, we treat the
			// directory "UnknownAccessLogs" special so that we show it last.
			if( $entry === $UnknownDirName ) {
				// ok - we're going to handle the "Unknown" directory last
				$unknownDirSeen = 1;		// remember to put this on the end of our list...
				continue;
			}
		   if (is_dir($entry)) {
				$dirs[] = $entry; 
		   }
		}
	}
	if( $unknownDirSeen ) {
		$dirs[] = $UnknownDirName;
	}
	return $dirs;
} // end of GetListOfDirs()




/*
 * GenerateHTMLHead - generate the beginning of our HTML generated page.
 *
 */
function GenerateHTMLHead( ) {
	global $dateTime;
	global $monthsCovered, $logsDir;

	?>
	<!DOCTYPE html>
	<meta charset="utf-8">
	<html lang="en" class="no-js">
	<head>
		<title>Access to our Applications on <?php echo $_SERVER['SERVER_NAME'] ?></title>
	</head>
	<body>
	<center>
		<h1>Access to our Applications on <?php echo $_SERVER['SERVER_NAME'] ?></h1>
		<div style="text-align: center;">(Generated on <?php echo $dateTime ?>)</div>
	<?php
	
	if( $monthsCovered ) {
		?>
		<div style="text-align: center;">(Covering only these months: <?php echo $monthsCovered ?>)</div>
		<?php
	}
	if( $logsDir ) {
		?>
		<div style="text-align: center;">(Using historical logs here: ./<?php echo $logsDir ?>)</div>
		<?php
	}
	
	?>
	</center>
	<p>
	<?php
} // end of US_GeneratePageHead()



/*
 * GenerateHTMLTail - generate the end of our HTML generated page.
 *
 */
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
	    $headerList['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];
	}
    //Return the array.
    return $headerList;
} // end of GetHeaderList()

?>