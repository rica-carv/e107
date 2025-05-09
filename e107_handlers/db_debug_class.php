<?php
	/*
	+ ----------------------------------------------------------------------------+
	|     e107 website system
	|
	|     Copyright (C) 2008-2009 e107 Inc (e107.org)
	|     http://e107.org
	|
	|
	|     Released under the terms and conditions of the
	|     GNU General Public License (http://gnu.org).
	|
	|     $Source: /cvs_backup/e107_0.8/e107_handlers/db_debug_class.php,v $
	|     $Revision$
	|     $Date$
	|     $Author$
	+----------------------------------------------------------------------------+
	*/

	if(!defined('e107_INIT'))
	{
		exit;
	}


/**
 *
 */
class e107_db_debug
	{
        private $active       = false;      // true when debug is active.
		var $aSQLdetails      = array();     // DB query analysis (in pieces for further analysis)
		var $aDBbyTable       = array();
		var $aOBMarks         = array(0 => ''); // Track output buffer level at each time mark
		var $aMarkNotes       = array();      // Other notes can be added and output...
		var $aTimeMarks       = array();      // Overall time markers
		var $curTimeMark      = 'Start';
		var $nTimeMarks       = 0;            // Provide an array index for time marks. Stablizes 'current' function
		var $aGoodQueries     = array();
		var $aBadQueries      = array();
		var $scbbcodes        = array();
		var $scbcount;
		var $deprecated_funcs = array();
		var $aLog             = array();    // Generalized debug log (only seen during debug)
		var $aIncList         = array(); // Included files

		function __construct()
		{


		}


        /**
         * Set the active status of the debug logging.
         * When set to false, called methods within the class will by bypassed for better performance.
         * @param $var
         */
        public function active($var)
        {
            $this->active = (bool) $var;
        }

		/**
		 * Return a list of all registered time markers.
		 * @return array
		 */
		public function getTimeMarkers()
		{
			return $this->aTimeMarks;
		}

		/**
		 * Return a list of currently logged items
		 * @return array
		 */
		public function getLog()
		{
			return $this->aLog;
		}



	/**
	 * @return void
	 */
	function e107_db_debug()
		{

			global $eTimingStart;

			$this->aTimeMarks[0] = array(
				'Index'     => 0,
				'What'      => 'Start',
				'%Time'     => 0,
				'%DB Time'  => 0,
				'%DB Count' => 0,
				'Time'      => ($eTimingStart),
				'DB Time'   => 0,
				'DB Count'  => 0,
				'Memory'    => 0
			);

			register_shutdown_function('e107_debug_shutdown');
		}

//
// Add your new Show function here so it will display in debug output!
//
	/**
	 * @return void
	 */
	function Show_All()
		{

			$this->ShowIf('Debug Log', $this->Show_Log());
			$this->ShowIf('Traffic Counters', e107::getSingleton('e107_traffic')->Display());
			$this->ShowIf('Time Analysis', $this->Show_Performance());
			$this->ShowIf('SQL Analysis', $this->Show_SQL_Details());
			$this->ShowIf('Shortcodes / BBCode', $this->Show_SC_BB());
			$this->ShowIf('Paths', $this->Show_PATH());
			$this->ShowIf('Deprecated Methods/Functions', $this->Show_DEPRECATED());

			if(E107_DBG_INCLUDES)
			{
				$this->aIncList = get_included_files();
			}

			$this->ShowIf('Included Files: ' . count($this->aIncList), $this->Show_Includes());
		}

	/**
	 * @param $title
	 * @param $str
	 * @return void
	 */
	function ShowIf($title, $str)
		{

			if(!empty($str))
			{
				//e107::getRender()->setStyle('debug');
				echo "<h4>" . $title . "</h4>";
				echo $str;
				//e107::getRender()->tablerender($title, $str);
			}
		}

	/**
	 * @param $sMarker
	 * @return void
	 */
	function Mark_Time($sMarker)
        {
            $this->logTime($sMarker);
        }

		/**
		 * Replacement for $sql->db_Mark_Time()
		 * @param string $sMarker - a descriptive marker for the log
		 * @return null
		 */
		public function logTime($sMarker)
		{
		   if(!$this->active)
           {
                return null;
           }



			$timeNow = microtime();
			$nMarks = ++$this->nTimeMarks;

		//	 file_put_contents(e_LOG."debugClass.log",$nMarks."\t". $sMarker."\n", FILE_APPEND);

			if(!strlen($sMarker))
			{
				$sMarker = "Mark not set";
			}

			$srch = array('[', ']');
			$repl = array("<small>", "</small>");

			$this->aTimeMarks[$nMarks] = array(
				'Index'     => ($this->nTimeMarks),
				'What'      => str_replace($srch, $repl, $sMarker),
				'%Time'     => 0,
				'%DB Time'  => 0,
				'%DB Count' => 0,
				'Time'      => $timeNow,
				'DB Time'   => 0,
				'DB Count'  => 0,
				'Memory'    => ((function_exists("memory_get_usage")) ? memory_get_usage() : 0)
			);

			$this->aOBMarks[$nMarks] = ob_get_level() . '(' . ob_get_length() . ')';
			$this->curTimeMark = $sMarker;

			// Add any desired notes to $aMarkNotes[$nMarks]... e.g.
			//global $eTimingStart;
			//$this->aMarkNotes[$nMarks] .= "verify start: ".$eTimingStart."<br />";
		}


		/**
		 * @param $query
		 * @param $rli
		 * @param $origQryRes
		 * @param $aTrace
		 * @param $mytime
		 * @param $curtable
	 * @return null
		 */
		function Mark_Query($query, $rli, $origQryRes, $aTrace, $mytime, $curtable)
		{


            if(!$this->active)
            {

                return null;
            }
			//  global $sql;
			$sql = e107::getDb($rli);

			// Explain the query, if possible...
			list($qtype, $args) = explode(" ", ltrim($query), 2);

			unset($args);
			$nFields = 0;
			$bExplained = false;
			$ExplainText = '';
			// Note the subtle bracket in the second comparison! Also, strcasecmp() returns zero on match
			if(!strcasecmp($qtype, 'SELECT') || !strcasecmp($qtype, '(SELECT'))
			{    // It's a SELECT statement - explain it
				// $rli should always be set by caller
				//	$sQryRes = (is_null($rli) ? mysql_query("EXPLAIN {$query}") :  mysql_query("EXPLAIN {$query}", $rli));

				$sQryRes = $sql->gen("EXPLAIN {$query}");

				if($sQryRes)  // There's something to explain
				{
					//$nFields = mysql_num_fields($sQryRes);
					$nFields = $sql->columnCount(); // mysql_num_fields($sQryRes);
					$bExplained = true;
				}
			}
			else
			{    // Don't run 'EXPLAIN' on other queries
				$sQryRes = $origQryRes;            // Return from original query could be TRUE or a link resource if success

			}

			// Record Basic query info
			$sCallingFile = varset($aTrace[2]['file']);
			$sCallingLine = varset($aTrace[2]['line']);

			$dbQryCount = $sql->db_QueryCount();

			$t = &$this->aSQLdetails[$dbQryCount];
			$t['marker'] = $this->curTimeMark;
			$t['caller'] = "$sCallingFile($sCallingLine)";
			$t['query'] = $query;
			$t['ok'] = ($sQryRes !== false);
			$t['error'] = $sQryRes ? '' : $sql->getLastErrorText(); // mysql_error();
			$t['nFields'] = $nFields;
			$t['time'] = $mytime;


			if($bExplained)
			{
				$bRowHeaders = false;
				//  while ($row = @mysql_fetch_assoc($sQryRes))
				while($row = $sql->fetch())
				{
					if(!$bRowHeaders)
					{
						$bRowHeaders = true;
						$t['explain'] = "<tr><td><b>" . implode("</b></td><td><b>", array_keys($row)) . "</b></td></tr>\n";
					}
					$t['explain'] .= "<tr><td>" . implode("&nbsp;</td><td>", array_values($row)) . "&nbsp;</td></tr>\n";
				}
			}
			else
			{
				$t['explain'] = $ExplainText;
			}

			$this->aTimeMarks[$this->nTimeMarks]['DB Time'] += $mytime;
			$this->aTimeMarks[$this->nTimeMarks]['DB Count']++;

			if(array_key_exists($curtable, $this->aDBbyTable))
			{
				$this->aDBbyTable[$curtable]['DB Time'] += $mytime;
				$this->aDBbyTable[$curtable]['DB Count']++;


			}
			else
			{
				$this->aDBbyTable[$curtable]['Table'] = $curtable;
				$this->aDBbyTable[$curtable]['%DB Time'] = 0;  // placeholder
				$this->aDBbyTable[$curtable]['%DB Count'] = 0; // placeholder
				$this->aDBbyTable[$curtable]['DB Time'] = $mytime;
				$this->aDBbyTable[$curtable]['DB Count'] = 1;

			}

			return null;
		}


	/**
	 * @param $force
	 * @return false|string
	 */
	function Show_SQL_Details($force = false)
	{

		$sql = e107::getDb();
		//
		// Show stats from aSQLdetails array
		//
		if(!E107_DBG_SQLQUERIES && !E107_DBG_SQLDETAILS && ($force === false))
		{
			return false;
		}


		$text = '';
		$nQueries = $sql->db_QueryCount();

		if(!$nQueries)
		{
			return $text;
		}

		//
		// ALWAYS summarize query errors
		//
		$badCount = 0;
		$okCount = 0;
		
		$SQLdetails = $this->getSQLDetails();

		foreach($SQLdetails as $cQuery)
		{
			if($cQuery['ok'] == 1)
			{
				$okCount++;
			}
			else
			{
				$badCount++;
			}
			
		}

		if($badCount)
		{
			$text .= "\n<table class='table table-striped table-bordered'>\n";
			$text .= "<tr><th colspan='2'><b>$badCount Query Errors!</b></td></tr>\n";
			$text .= "<tr><th><b>Index</b></td><th><b>Query / Error</b></td></tr>\n";

			foreach($SQLdetails as $idx => $cQuery)
			{
				if(!$cQuery['ok'])
				{
					$text .= "<tr><td  rowspan='2' style='text-align:right'>{$idx}&nbsp;</td>
    	       	        <td>" . $cQuery['query'] . "</td></tr>\n<tr><td>" . $cQuery['error'] . "</td></tr>\n";
				}
			}
			$text .= "\n</table><br />\n";
		}

		//
		// Optionally list good queries
		//

		if($okCount && (E107_DBG_SQLDETAILS || $force))
		{
			$text .= "\n<table class='table table-striped table-bordered'>\n";
			$text .= "<tr><th colspan='3'><b>" . $this->countLabel($okCount) . " Good Queries</b></td></tr>\n";
			$text .= "<tr><th><b>Index</b></td><th><b>Qtime</b></td><th><b>Query</b></td></tr>\n
				 <tr><th>&nbsp;</td><th><b>(msec)</b></td><th>&nbsp;</td></tr>\n
				 ";

			$count = 0;
			foreach($SQLdetails as $idx => $cQuery)
			{
				if($count > 500)
				{
					$text .= "<tr class='danger'><td colspan='6'><b>Too many queries. Ending... </b></td></tr>"; // NO LAN - debug only.
					break;
				}


				if($cQuery['ok'])
				{
					$queryTime = ($cQuery['time'] * 1000.0);

					$class = ($queryTime > 100) ? 'text-danger' : '';
					$text .= "<tr><td  style='text-align:right'>{$idx}&nbsp;</td>
	       	        <td class='$class' style='text-align:right'>" . number_format($queryTime, 4) . "&nbsp;</td>
	       	        <td>" . $cQuery['query'] . '<br />[' . $cQuery['marker'] . " - " . $cQuery['caller'] . "]</td></tr>\n";

					$count++;
				}
			}


			$text .= "\n</table><br />\n";
		}

		//
		// Optionally list query details
		//
		if(E107_DBG_SQLDETAILS || $force)
		{
			$count = 0;
			foreach($SQLdetails as $idx => $cQuery)
			{
				$queryTime = ($cQuery['time'] * 1000.0);
				$class = ($queryTime > 100) ? 'text-danger' : '';

				$text .= "\n<table class='table table-striped table-bordered' style='width: 100%;'>\n";
				$text .= "<tr><td  colspan='" . $cQuery['nFields'] . "'><b>" . $idx . ") Query:</b> [" . $cQuery['marker'] . " - " . $cQuery['caller'] . "]<br />" . $cQuery['query'] . "</td></tr>\n";

				if(isset($cQuery['explain']))
				{
					$text .= $cQuery['explain'];
				}

				if(strlen($cQuery['error']))
				{
					$text .= "<tr><td  ><b>Error in query:</b></td></tr>\n<tr><td>" . $cQuery['error'] . "</td></tr>\n";
				}

				$text .= "<tr><td class='$class'  colspan='" . $cQuery['nFields'] . "'><b >Query time:</b> " . number_format($cQuery['time'] * 1000.0, 4) . ' (ms)</td></tr>';

				$text .= '</table><br />' . "\n";

				if($count > 500)
				{
					$text .= "<div class='alert alert-danger text-center'>Too many queries. Ending...</div>"; // NO LAN - debug only.
					break;
				}


				$count++;
			}
		}

		return $text;
	}

	public function getSQLDetails()
	{
		return $this->aSQLdetails;
	}

	public function setSQLDetails($aSQLdetails)
	{
		$this->aSQLdetails = $aSQLdetails ?? [];
	}


	/**
	 * @param $amount
	 * @return string
	 */
	function countLabel($amount)
	{

		$inc = '';

		if($amount < 30)
		{
			$inc = 'label-success';
		}
		elseif($amount < 50)
		{
			$inc = 'label-warning';
		}
		elseif($amount > 49)
		{
			$inc = 'label-danger label-important';
		}

		return "<span class='label " . $inc . "'>" . $amount . "</span>";
	}


	/**
	 * @param $log
	 * @return void
	 */
	function save($log)
		{

			e107::getMessage()->addDebug("Saving a log");

			$titles = array_keys($this->aTimeMarks[0]);

			$text = implode("\t\t\t", $titles) . "\n\n";

			foreach($this->aTimeMarks as $item)
			{
				$item['What'] = str_pad($item['What'], 50, " ", STR_PAD_RIGHT);
				$text .= implode("\t\t\t", $item) . "\n";
			}

			file_put_contents($log, $text, FILE_APPEND);

		}


	/**
	 * @param $label
	 * @param $value
	 * @param $thresholdHigh
	 * @return mixed|string
	 */
	private function highlight($label, $value = 0, $thresholdHigh = 0)
		{

			if($value > $thresholdHigh)
			{
				return "<span class='label label-danger' style='font-size:0.9em'>" . $label . "</span>";
			}

			if($value == 0)
			{
				return "<span class='label label-success'>" . $label . "</span>";
			}

			return $label;

		}


	/**
	 * @return string
	 */
	function Show_Performance()
		{

			//
			// Stats by Time Marker
			//
			global $db_time;
			global $sql;
			global $eTimingStart, $eTimingStop;

			$this->Mark_Time('Stop');

			if(!E107_DBG_TIMEDETAILS)
			{
				return '';
			}

			$totTime = e107::getSingleton('e107_traffic')->TimeDelta($eTimingStart, $eTimingStop);

			$text = "\n<table class='table table-striped table-condensed'>\n";
			$bRowHeaders = false;
			reset($this->aTimeMarks);
			$aSum = $this->aTimeMarks[0]; // create a template from the 'real' array

			$aSum['Index'] = '';
			$aSum['What'] = 'Total';
			$aSum['Time'] = 0;
			$aSum['DB Time'] = 0;
			$aSum['DB Count'] = 0;
			$aSum['Memory'] = 0;

			// Calculate Memory Usage per entry.
			$prevMem = 0;

			foreach($this->aTimeMarks as $k => $v)
			{

				$prevKey = $k - 1;

				if(!empty($prevKey))
				{
					$this->aTimeMarks[$prevKey]['Memory Used'] = (intval($v['Memory']) - $prevMem);
				}

				$prevMem = intval($v['Memory']);
			}




			$obj = new ArrayObject($this->aTimeMarks);
			$it = $obj->getIterator();
			$head = '';

		    // while(list($tKey, $tMarker) = each($this->aTimeMarks))
			foreach ($it as $tKey=>$tMarker)
			{

				if(!$bRowHeaders)
				{
					// First time: emit headers
					$bRowHeaders = true;
					$head = "<tr><th style='text-align:right'><b>" . implode("</b>&nbsp;</td><th style='text-align:right'><b>", array_keys($tMarker)) . "</b>&nbsp;</td><th style='text-align:right'><b>OB Lev&nbsp;</b></td></tr>\n";
					$aUnits = $tMarker;
					foreach($aUnits as $key => $val)
					{
						switch($key)
						{
							case 'DB Time':
							case 'Time':
								$aUnits[$key] = '(msec)';
								break;
							default:
								$aUnits[$key] = '';
								break;
						}
					}
					$aUnits['OB Lev'] = 'lev(buf bytes)';
					$aUnits['Memory'] = '(kb)';
					$aUnits['Memory Used'] = '(kb)';
					$head .= "<tr><th style='text-align:right'><small>" . implode("</small>&nbsp;</td><th style='text-align:right'><small>", $aUnits) . "</small>&nbsp;</td></tr>\n";
					$text .= $head;
				}


				//	$tMem =   ($tMarker['Memory'] - $aSum['Memory']);

				$tMem = ($tMarker['Memory']);

		//		if($tMem < 0) // Quick Fix for negative numbers.
			//	{
					//	$tMem = 0.0000000001;
			//	}

				$tMarker['Memory'] = ($tMem ? number_format($tMem / 1024.0, 1) : '?'); // display if known

				$tUsage = $tMarker['Memory Used'];
				$tMarker['Memory Used'] = number_format($tUsage / 1024.0, 1);

				$tMarker['Memory Used'] = $this->highlight($tMarker['Memory Used'], $tUsage, 400000);
				/*
								if($tUsage > 400000) // Highlight high memory usage.
								{
									$tMarker['Memory Used'] = "<span class='label label-danger'>".$tMarker['Memory Used']."</span>";
								}*/

				$aSum['Memory'] = $tMem;

				if($tMarker['What'] === 'Stop')
				{
					$tMarker['Time'] = '&nbsp;';
					$tMarker['%Time'] = '&nbsp;';
					$tMarker['%DB Count'] = '&nbsp;';
					$tMarker['%DB Time'] = '&nbsp;';
					$tMarker['DB Time'] = '&nbsp;';
					$tMarker['OB Lev'] = $this->aOBMarks[$tKey];
					$tMarker['DB Count'] = '&nbsp;';
				}
				else
				{
					// Convert from start time to delta time, i.e. from now to next entry
				//	$nextMarker = current($this->aTimeMarks);
					$it->next();
					$nextMarker = $it->current();
					$it->seek($tKey - 1); // go back one position.

					$aNextT = $nextMarker['Time'];
					$aThisT = $tMarker['Time'];


					$thisDelta = e107::getSingleton('e107_traffic')->TimeDelta($aThisT, $aNextT);
					$aSum['Time'] += $thisDelta;
					$aSum['DB Time'] += $tMarker['DB Time'];
					$aSum['DB Count'] += $tMarker['DB Count'];
					$tMarker['Time'] = number_format($thisDelta * 1000.0, 1);
					$tMarker['Time'] = $this->highlight($tMarker['Time'], $thisDelta, .2);


					$tMarker['%Time'] = $totTime ? number_format(100.0 * ($thisDelta / $totTime), 0) : 0;
					$tMarker['%Time'] = $this->highlight($tMarker['%Time'], $tMarker['%Time'], 20);

					$tMarker['%DB Count'] = number_format(100.0 * $tMarker['DB Count'] / $sql->db_QueryCount(), 0);
					$tMarker['%DB Time'] = $db_time ? number_format(100.0 * $tMarker['DB Time'] / $db_time, 0) : 0;
				//	$tMarker['DB Time'] = number_format($tMarker['DB Time'] * 1000.0, 1);
					$dbTime = number_format($tMarker['DB Time'] * 1000.0, 1);
					$tMarker['DB Time'] = $this->highlight($dbTime,$dbTime, 10);

					$tMarker['OB Lev'] = $this->aOBMarks[$tKey];
				}

				$text .= "<tr><td  >" . implode("&nbsp;</td><td   style='text-align:right'>", array_values($tMarker)) . "&nbsp;</td></tr>\n";

				if(isset($this->aMarkNotes[$tKey]))
				{
					$text .= "<tr><td  >&nbsp;</td><td  colspan='4'>";
					$text .= $this->aMarkNotes[$tKey] . "</td></tr>\n";
				}

				if($tMarker['What'] === 'Stop')
				{
					break;
				}
			}

			$text .= $head;

			$aSum['%Time'] = $totTime ? number_format(100.0 * ($aSum['Time'] / $totTime), 0) : 0;
			$aSum['%DB Time'] = $db_time ? number_format(100.0 * ($aSum['DB Time'] / $db_time), 0) : 0;
			$aSum['%DB Count'] = ($sql->db_QueryCount()) ? number_format(100.0 * ($aSum['DB Count'] / ($sql->db_QueryCount())), 0) : 0;
			$aSum['Time'] = number_format($aSum['Time'] * 1000.0, 1);
			$aSum['DB Time'] = number_format($aSum['DB Time'] * 1000.0, 1);


			$text .= "<tr>
		<th>&nbsp;</td>
		<th style='text-align:right'><b>Total</b></td>
		<th style='text-align:right'><b>" . $aSum['%Time'] . "</b></td>
		<th style='text-align:right'><b>" . $aSum['%DB Time'] . "</b></td>
		<th style='text-align:right'><b>" . $aSum['%DB Count'] . "</b></td>
		<th style='text-align:right' title='Time (msec)'><b>" . $aSum['Time'] . "</b></td>
		<th style='text-align:right' title='DB Time (msec)'><b>" . $aSum['DB Time'] . "</b></td>
		<th style='text-align:right'><b>" . $aSum['DB Count'] . "</b></td>
		<th style='text-align:right' title='Memory (Kb)'><b>" . number_format($aSum['Memory'] / 1024, 1) . "</b></td>
		<th style='text-align:right' title='Memory (Kb)'><b>" . number_format($aSum['Memory'] / 1024, 1) . "</b></td>

			<th style='text-align:right'><b>" . $tMarker['OB Lev'] . "</b></td>

		</tr>
		";


			//	$text .= "<tr><th><b>".implode("</b>&nbsp;</td><th style='text-align:right'><b>", $aSum)."</b>&nbsp;</td><th>&nbsp;</td></tr>\n";

			$text .= "\n</table><br />\n";


			//
			// Stats by Table
			//

			$text .= "\n<table class='table table-striped table-condensed'>
			<colgroup>
				<col style='width:auto' />
				<col style='width:9%' />
					<col style='width:9%' />
						<col style='width:9%' />
							<col style='width:9%' />
			</colgroup>\n";

			$bRowHeaders = false;
			$aSum = $this->aDBbyTable['core']; // create a template from the 'real' array
			$aSum['Table'] = 'Total';
			$aSum['%DB Time'] = 0;
			$aSum['%DB Count'] = 0;

			$aSum['DB Time'] = 0;
			$aSum['DB Count'] = 0;

			foreach($this->aDBbyTable as $curTable)
			{
				if(!$bRowHeaders)
				{
					$bRowHeaders = true;
					$text .= "<tr><th><b>" . implode("</b></td><th style='text-align:right'><b>", array_keys($curTable)) . "</b></td></tr>\n";
					$aUnits = $curTable;
					foreach($aUnits as $key => $val)
					{
						switch($key)
						{
							case 'DB Time':
								$aUnits[$key] = '(msec)';
								break;
							default:
								$aUnits[$key] = '';
								break;
						}
					}
					$text .= "<tr><th style='text-align:right'><b>" . implode("</b>&nbsp;</td><th style='text-align:right'><b>", $aUnits) . "</b>&nbsp;</td></tr>\n";
				}

				$aSum['DB Time'] += $curTable['DB Time'];
				$aSum['DB Count'] += $curTable['DB Count'];
				$curTable['%DB Count'] = number_format(100.0 * $curTable['DB Count'] / $sql->db_QueryCount(), 0);
				$curTable['%DB Time'] = number_format(100.0 * $curTable['DB Time'] / $db_time, 0);
				$timeLabel = number_format($curTable['DB Time'] * 1000.0, 1);
				$curTable['DB Time'] = $this->highlight($timeLabel, ($curTable['DB Time'] * 1000), 500); // 500 msec

				$text .= "<tr><td>" . implode("&nbsp;</td><td  style='text-align:right'>", array_values($curTable)) . "&nbsp;</td></tr>\n";
			}

			$aSum['%DB Time'] = $db_time ? number_format(100.0 * ($aSum['DB Time'] / $db_time), 0) : 0;
			$aSum['%DB Count'] = ($sql->db_QueryCount()) ? number_format(100.0 * ($aSum['DB Count'] / ($sql->db_QueryCount())), 0) : 0;
			$aSum['DB Time'] = number_format($aSum['DB Time'] * 1000.0, 1);
			$text .= "<tr><th><b>" . implode("&nbsp;</td><th style='text-align:right'><b>", array_values($aSum)) . "&nbsp;</b></td></tr>\n";
			$text .= "\n</table><br />\n";

			return $text;
		}

	/**
	 * @param $message
	 * @param $file
	 * @param $line
	 * @return void|null
	 */
	function logDeprecated($message=null, $file=null, $line=null)
		{
			if(!empty($message) || !empty($file))
			{
				$this->deprecated_funcs[] = array(
					'func' => $message,
					'file' => $file,
					'line' => $line
				);

				return null;
			}


			$back_trace = debug_backtrace();

		//	print_r($back_trace);

			$this->deprecated_funcs[] = array(
				'message'   => varset($message),
				'func' => (isset($back_trace[1]['type']) && ($back_trace[1]['type'] === '::' || $back_trace[1]['type'] === '->') ? $back_trace[1]['class'] . $back_trace[1]['type'] . $back_trace[1]['function'] : $back_trace[1]['function']),
				'file' => $back_trace[1]['file'],
				'line' => $back_trace[1]['line']
			);

		}

	/**
	 * @param $type
	 * @param $code
	 * @param $parm
	 * @param $details
	 * @return false|null
	 */
	function logCode($type, $code, $parm, $details)
		{

			if(!E107_DBG_BBSC || !$this->active)
			{
				return false;
			}

			$this->scbbcodes[$this->scbcount]['type'] = $type;
			$this->scbbcodes[$this->scbcount]['code'] = $code;
			$this->scbbcodes[$this->scbcount]['parm'] = is_array($parm) ? var_export($parm, true) : (string) $parm;
			$this->scbbcodes[$this->scbcount]['details'] = $details;
			$this->scbcount++;

			return null;
		}

	/**
	 * @param $force
	 * @return false|string
	 */
	function Show_SC_BB($force = false)
		{

			if(!E107_DBG_BBSC && ($force === false))
			{
				return false;
			}

			$text = "<table class='table table-striped table-condensed' style='width: 100%'>
			
			<thead>
			<tr>
				<th style='width: 10%;'>Type</th>
				<th style='width: 30%;'>Code</th>
				<th style='width: 20%;'>Parm</th>
				<th style='width: 40%;'>Details</th>
			</tr>
			</thead>
			<tbody>\n";

			$description = array(1 => 'Bbcode', 2 => 'Shortcode', 3 => 'Wrapper', 4 => 'Shortcode Override', -2 => 'Shortcode Failure');
			$style = array(1 => 'label-info', 2 => 'label-primary', 3 => 'label-warning', 'label-danger', -2 => 'label-danger');

			foreach($this->scbbcodes as $codes)
			{

				$type = $codes['type'];

				$text .= "<tr>
				<td  style='width: 10%;'><span class='label " . $style[$type] . "'>" . ($description[$type]) . "</span></td>
				<td  style='width: auto;'>" . (isset($codes['code']) ? $codes['code'] : "&nbsp;") . "</td>
				<td  style='width: auto;'>" . ($codes['parm'] ? $codes['parm'] : "&nbsp;") . "</td>
				<td  style='width: 40%;'>" . ($codes['details'] ? $codes['details'] : "&nbsp;") . "</td>
				</tr>\n";
			}
			$text .= "</tbody></table>";

			return $text;
		}

	/**
	 * @param $value
	 * @return bool
	 */
	private function includeVar($value)
		{
			$prefix = array('ADMIN', 'E_', 'e_', 'E107', 'SITE', 'USER', 'CORE');

			foreach($prefix as $c)
			{
				if(strpos($value, $c) === 0)
				{
					return true;
				}
			}

			return false;
		}

	/**
	 * @param $force
	 * @return false|string
	 */
	function Show_PATH($force = false)
		{

			if(!E107_DBG_PATH && ($force === false))
			{
				return false;
			}


			$e107 = e107::getInstance();
			$sql = e107::getDb();

			$text = "<table class='table table-striped table-condensed debug-footer' style='width:100%'>
		<colgroup>
		<col style='width:20%' />
		<col style='width:auto' />
		</colgroup>
		<thead>
			<tr>
				<th class='debug-footer-caption left' colspan='2'><b>Paths &amp; Variables</b></th>
			</tr>
		</thead>
		<tbody>\n";


			$inc = array(
				'BOOTSTRAP', 'HEADERF', 'FOOTERF', 'FILE_UPLOADS', 'FLOODPROTECT', 'FLOODTIMEOUT', 'FONTAWESOME', 'CHARSET',
				'GUESTS_ONLINE', 'MEMBERS_ONLINE', 'PAGE_NAME', 'STANDARDS_MODE', 'TIMEOFFSET',
				'TOTAL_ONLINE', 'THEME', 'THEME_ABS', 'THEME_LAYOUT', 'THEME_LEGACY', 'THEME_VERSION',  'THEME_STYLE', 'META_OG', 'META_DESCRIPTION', 'MPREFIX', 'VIEWPORT', 'BODYTAG', 'CSSORDER'
			);

			$userCon = get_defined_constants(true);
			ksort($userCon['user']);


			$c = 0;
			foreach($userCon['user'] as $k => $v)
			{
				if(E107_DBG_ALLERRORS || in_array($k, $inc) || $this->includeVar($k))
				{
					$text .= "
				<tr>
					<td>" . $k . "</td>
					<td>" . (is_string($v) ? htmlspecialchars($v) : $v). "</td>
				</tr>";
					$c++;
				}
			}

			$sess = e107::getSession();

			$text .= "
			
		
			<tr>
				<td>SQL Language</td>
				<td>" . $sql->mySQLlanguage . "</td>
			</tr>
			
			
";
			if($_SERVER['E_DEV'] == 'true')
			{
				$text .= "
				<tr>
					<th colspan='2'><h2>e107 Object</h2></td>
				</tr>";

					foreach($e107 as $key=>$val)
					{
						$text .= "
						<tr>
							<td>".$key."</td>
							<td><pre>" . htmlspecialchars(print_r($val, true)) . "</pre></td>
						</tr>";
					}
			}

			$text .= "
			<tr>
				<th colspan='2'><h2>Registry</h2></td>
			</tr>";

			$regis = e107::getRegistry('_all_');
			ksort($regis);
			$c = 0;
			foreach($regis as $key=>$val)
			{
				$id = "view-registry-".$c;

				$text .= "<tr>
				<td>".$key."</td>
				<td><a href='#".$id."' class='btn btn-sm btn-default e-expandit'>View</a><div id='".$id."' class='e-hideme'>" . print_a($val,true). "</div></td>
				</tr>";
				$c++;

			}

			$text .= "
			
			<tr>
				<th colspan='2'><h2>Session</h2></td>
			</tr>
			<tr>
				<td>Session lifetime</td>
				<td>" . $sess->getOption('lifetime') . " seconds</td>
			</tr>
			<tr>
				<td>Session domain</td>
				<td>" . $sess->getOption('domain') . "</td>
			</tr>
			<tr>
				<td>Session save method</td>
				<td>" . $sess->getSaveMethod() . "</td>
			</tr>
			
			<tr>
				<td>Registry</td>
				<td>" . $sess->getSaveMethod() . "</td>
			</tr>
			
			
			
			
			<tr>
				<td  colspan='2'><pre>" . htmlspecialchars(print_r($_SESSION, true)) . "</pre></td>
			</tr>
			
		</tbody>
		</table>";

			return $text;
		}


	/**
	 * @param $force
	 * @return false|string
	 */
	function Show_DEPRECATED($force = false)
		{
			if(!E107_DBG_DEPRECATED && ($force === false))
			{
				return false;
			}

			$text = "
				<table class='table table-striped table-condensed' style='width: 100%'>
				
				<thead>
				<tr>
				<th style='width: 40%;'>Info</th>
				<th style='width: 30%;'>File</th>
				<th>Line</th>
				</tr>
				</thead>
				<tbody>\n";

				if(empty($this->deprecated_funcs))
				{
					$text .= "<tr>
						<td colspan='3'><span class='label label-success'>None Found</span></td>
						</tr>";
				}
				else
				{

				//	$unique = array_unique($this->deprecated_funcs);

					foreach($this->deprecated_funcs as $funcs)
					{
						$text .= "<tr>
						<td>{$funcs['func']}</td>
						<td>".(is_array($funcs['file']) ? print_a($funcs['file'],true) : $funcs['file'])."</td>
						<td>{$funcs['line']}</td>
						</tr>\n";
					}
				}

				$text .= "</tbody></table>";

				return $text;

		}


		/**
		 * var_dump to debug log
		 *
		 * @param mixed $message
		 */
		function dump($message, $TraceLev = 1)
		{

			ob_start();
			var_dump($message);
			$content = ob_get_clean();

			$bt = debug_backtrace();

			$this->aLog[] = array(
				'Message'  => $content,
				'Function' => (isset($bt[$TraceLev]['type']) && ($bt[$TraceLev]['type'] == '::' || $bt[$TraceLev]['type'] == '->') ? $bt[$TraceLev]['class'] . $bt[$TraceLev]['type'] . $bt[$TraceLev]['function'] . '()' : $bt[$TraceLev]['function']) . '()',
				'File'     => varset($bt[$TraceLev]['file']),
				'Line'     => varset($bt[$TraceLev]['line'])
			);

			// $this->aLog[] =	array ('Message'   => $content, 'Function' => '', 	'File' => '', 'Line' => '' 	);

		}


		/**
		 * @desc Simple debug-level 'console' log
		 * Record a "nice" debug message with
		 * $db_debug->log("message");
		 * @param string|array    $message
		 * @param int $TraceLev
		 * @return bool|null  true on success , false on error
		 */
		public function log($message, $TraceLev = 1)
		{
		    if(!$this->active)
            {
                return null;
            }

			if(!is_string($message))
			{
				$message = "<pre>" . var_export($message, true) . "</pre>";
			}

			if(!deftrue('E107_DBG_BASIC') && !deftrue('E107_DBG_ALLERRORS') && !deftrue('E107_DBG_SQLDETAILS') && !deftrue('E107_DBG_NOTICES'))
			{
				return false;
			}

			if($TraceLev)
			{
				$bt = debug_backtrace();
				$this->aLog[] = array(
					'Message'  => $message,
					'Function' => (isset($bt[$TraceLev]['type']) && ($bt[$TraceLev]['type'] == '::' || $bt[$TraceLev]['type'] == '->') ? $bt[$TraceLev]['class'] . $bt[$TraceLev]['type'] . $bt[$TraceLev]['function'] . '()' : $bt[$TraceLev]['function']) . '()',
					'File'     => varset($bt[$TraceLev]['file']),
					'Line'     => varset($bt[$TraceLev]['line'])
				);
			}
			else
			{
				$this->aLog[] = array(
					'Message'  => $message,
					'Function' => '',
					'File'     => '',
					'Line'     => ''
				);
			}

			return true;
		}

		/**
		 * @return bool|string
		 */
		function Show_Log()
		{

			if(empty($this->aLog))
			{
				return false;
			}
			//
			// Dump the debug log
			//

			$text = "\n<table class='table table-striped'>\n";

			$bRowHeaders = false;

			foreach($this->aLog as $curLog)
			{
				if(!$bRowHeaders)
				{
					$bRowHeaders = true;
					$text .= "<tr><th style='text-align:left'><b>" . implode("</b></td><th style='text-align:left'><b>", array_keys($curLog)) . "</b></td></tr>\n";
				}

				$text .= "<tr ><td>" . implode("&nbsp;</td><td>", array_values($curLog)) . "&nbsp;</td></tr>\n";
			}

			$text .= "</table><br />\n";

			return $text;
		}

	/**
	 * @param $force
	 * @return false|string
	 */
	function Show_Includes($force = false)
		{

			if(!E107_DBG_INCLUDES && ($force === false))
			{
				return false;
			}


			$text = "<table class='table table-striped'>\n";
			$text .= "<tr><td>" .
				implode("&nbsp;</td></tr>\n<tr><td>", $this->aIncList) .
				"&nbsp;</td></tr>\n";
			$text .= "</table>\n";

			return $text;
		}
	}


//
// Helper functions (not part of the class)
//
/**
 * @return void
 */
function e107_debug_shutdown()
	{

		if(e_AJAX_REQUEST) // extra output will break json ajax returns ie. comments
		{
			return;
		}


		global $error_handler;
		

		if(!empty($GLOBALS['E107_CLEAN_EXIT']))
		{
			return;
		}

		if(empty($GLOBALS['E107_IN_FOOTER']))
		{
			$ADMIN_DIRECTORY = e107::getFolder('admin');

			if(deftrue('ADMIN_AREA'))
			{
				$filewanted = realpath(__DIR__) . '/../' . $ADMIN_DIRECTORY . 'footer.php';
				require_once($filewanted);
			}
			elseif(deftrue('USER_AREA'))
			{
				$filewanted = realpath(__DIR__) . '/../' . FOOTERF;
				require_once($filewanted);
			}
		}
// 
// Error while in the footer, or during startup, or during above processing
//
		if(!empty($GLOBALS['E107_CLEAN_EXIT']))
		{
			return;
		}

		while(ob_get_level() > 0)
		{
			ob_end_flush();
		}

		if(isset($error_handler))
		{
			if($error_handler->return_errors())
			{
				echo "<br /><div 'e107_debug php_err'><h3>PHP Errors:</h3><br />" . $error_handler->return_errors() . "</div>\n";
				echo "</body></html>";
			}
		}
		else
		{
			echo "<b>e107 Shutdown while no error_handler available!</b>";
			echo "</body></html>";
		}

	}

