<?php
/*
=====================================================
 This plugin was created by Lodewijk Schutte
 - freelance@loweblog.com
 - http://loweblog.com/freelance/
=====================================================
 File: pi.yearly_archives.php
-----------------------------------------------------
 Purpose: Displays yearly archives
=====================================================
*/

$plugin_info = array(
				 'pi_name'			=> 'Yearly Archives',
				 'pi_version'		=> '1.7',
				 'pi_author'		=> 'Lodewijk Schutte',
				 'pi_author_url'	=> 'http://loweblog.com/',
				 'pi_description'	=> 'For displaying yearly archives',
				 'pi_usage'			=> yearly_archives::usage()
				);


class Yearly_archives {

	var $return_data;
	
	// ----------------------------------------
	//	Yearly Archives, yes please!
	// ----------------------------------------

	function Yearly_archives()
	{
		global $LOC, $DB, $TMPL, $FNS, $LANG, $PREFS;
		
		// get some params
		$status	= $TMPL->fetch_param('status')	? $TMPL->fetch_param('status')	: 'open';
		$weblog	= $TMPL->fetch_param('weblog')	? $TMPL->fetch_param('weblog')	: '';
		$site_id= $TMPL->fetch_param('site_id')	? $TMPL->fetch_param('site_id')	: $PREFS->ini('site_id');
		
		// init timeframe, get some more params
		$timeframe = array(
			'start_year'	=> $TMPL->fetch_param('start_year'),
			'end_year'		=> $TMPL->fetch_param('end_year'),
			'start_month'	=> $TMPL->fetch_param('start_month'),	
			'end_month'		=> $TMPL->fetch_param('end_month')
		);
		
		// build sql where arguments
		$sql_now	 = $DB->escape_str($LOC->now);
		$sql_expired = ($TMPL->fetch_param('show_expired') == 'yes')		? "" : "AND ( expiration_date = 0 OR expiration_date > '{$sql_now}' )";
		$sql_future	 = ($TMPL->fetch_param('show_future_entries') == 'yes')	? "" : "AND entry_date < '{$sql_now}'";
		$sql_status	 = $FNS->sql_andor_string($status, 'status');
		$sql_weblog	 = $FNS->sql_andor_string($weblog, 'blog_name');
		$sql_site_id = $DB->escape_str($site_id);
		
		// no category? Don't join.
		if ($category = $TMPL->fetch_param('category'))
		{
			$sql_cat_join = "INNER JOIN exp_category_posts p ON t.entry_id = p.entry_id";
			$sql_category = $FNS->sql_andor_string($category, 'p.cat_id');
		}
		else
		{
			$sql_cat_join = $sql_category = '';
		}

		// query weblog titles - just the one query for checking whether entries exist. Efficiency is our friend.
		$sql = "
			SELECT
				CONCAT(t.year,t.month) AS ym,
				COUNT(*) AS num_entries
			FROM
				exp_weblog_titles t
			INNER JOIN
				exp_weblogs w
			ON
				t.weblog_id = w.weblog_id
				{$sql_cat_join}
			WHERE
				w.site_id = '{$sql_site_id}'
				{$sql_category}
				{$sql_expired}
				{$sql_future}
				{$sql_status}
				{$sql_weblog}
			GROUP BY
				ym
			ORDER BY
				ym ASC
		";
		$query = $DB->query($sql);

		// loop thru results and drop 'em like it's hot
		$months = array();
		$years	= array();
		foreach ($query->result AS $row)
		{
			$months[$row['ym']] = $row['num_entries'];
			
			$tmp_year = substr($row['ym'],0,4);
			if (!isset($years[$tmp_year])) { $years[$tmp_year] = 0; }
			$years[$tmp_year] += $row['num_entries'];
		}
		
		// Override init vars
		if ($query->num_rows > 0)
		{
			$first = key($months);	// get first key = first month
			end($months);			// go to the last element in array
			$last  = key($months);	// and get that key too

			if (!$timeframe['start_year'])	{ $timeframe['start_year']	= substr($first, 0, 4);	}
			if (!$timeframe['end_year'])	{ $timeframe['end_year']	= substr($last,	0, 4);	}
			if (!$timeframe['start_month'])	{ $timeframe['start_month']	= substr($first, -2);	}
			if (!$timeframe['end_month'])	{ $timeframe['end_month']	= substr($last,	-2);	}
		}
		else
		{
			// nothing found? Meh.
			return;
		}
		
		// ASC or DESC, to put in range() function. Notice how we don't use anymore queries.
		if ($TMPL->fetch_param('sort') == 'asc')
		{
			// if ASC, we start with start, and end with end.
			$s = 'start';
			$e = 'end';
		}
		else
		{
			// if DESC, we start with end, and end with start. Get it? :)
			$s = 'end';
			$e = 'start';
		}
		
		// Compose nice little nested array with all the results - loop thru years first
		$result = array();
		foreach (range( intval($timeframe[$s.'_year']), intval($timeframe[$e.'_year']) ) AS $year)
		{
			// init result entry
			$result[$year] = array();
			
			// init months
			$ms = 01;	// first month
			$me = 12;	// last month - Duh
		
			// override month start if we're at the start year and a start month has been defined
			if ($year == $timeframe['start_year'] && $timeframe['start_month'] >= 1 && $timeframe['start_month'] <= 12)
			{
				$ms = $timeframe['start_month'];
			}

			// override month end if we're at the end year and a end month has been defined
			if ($year == $timeframe['end_year'] && $timeframe['end_month'] >= 1 && $timeframe['end_month'] <= 12)
			{
				$me = $timeframe['end_month'];
			}
			
			// month sorting
			if ($TMPL->fetch_param('monthsort') == 'desc')
			{
				$monthstart	= 'me';
				$monthend	= 'ms';
			}
			else
			{
				$monthstart	= 'ms';
				$monthend	= 'me';
			}
			
			// init month count
			$month_count = 0;

			// then loop thru the months of current year and put vars in result array
			foreach (range( intval($$monthstart), intval($$monthend) ) AS $month)
			{
				// leading zero...
				if (strlen($month) == 1) { $month = '0'.$month; }
				
				// nice month names
				$tmp_month = $LOC->localize_month($month);
				
				// result array
				$data = array(
					'month'				=> $LANG->line($tmp_month[1]),
					'month_num'			=> $month,
					'month_short'		=> $LANG->line($tmp_month[0]),
					'month_num_short'	=> intval($month),
					'month_count'		=> ++$month_count,
					'num_entries'		=> ((isset($months[$year.$month])) ? $months[$year.$month] : 0), // got entries for current month?
					'num_entries_percentage' => 0,
					'num_entries_percentage_rounded' => 0
				);
				
				// Courtesy of Leevi Graham
				if ($data['num_entries'] > 0 && isset($years[$year]) && $years[$year] > 0)
				{
					$data['num_entries_percentage'] = $data['num_entries'] / $years[$year] * 100;
					$data['num_entries_percentage_rounded'] = round($data['num_entries_percentage']);
				}
				
				$result[$year][] = $data;
			}
		}
		// done looping thru years
		
		// How many years are there? Init year count
		$total_years = count($result);
		$year_count  = 0;
		
		// Now then, loop thru results and do the template thing
		foreach ($result AS $year => $data)
		{
			$tagdata = $TMPL->tagdata;
			
			// vars available outside the {months} var pair
			$row = array(
				'year'				=> $year,
				'year_short'		=> substr($year, -2),
				'total_years'		=> $total_years,
				'year_count'		=> ++$year_count,
				'total_months'		=> count($data),
				'entries_in_year'	=> (isset($years[$year]) ? $years[$year] : 0),
				'leap_year'			=> date("L", strtotime("{$year}-01-01 12:00:00")) // leap_year: 1 or 0
			);
			
			// COND VARS - whee! How easy was that!
			$tagdata = $FNS->prep_conditionals($tagdata, $row);
			
			// SINGLE VARS
			foreach ($TMPL->var_single as $key => $val)
			{
				if (isset($row[$val]))
				{
					$tagdata = $TMPL->swap_var_single($val, $row[$val], $tagdata);
				}
			}
			
			// VAR PAIRS
			foreach ($TMPL->var_pair as $key => $val)
			{
				// months var pair		 
				if (substr($key, 0, 6) == 'months')
				{
					// got something?
					if (count($data))
					{
						$var_pair_template	= $TMPL->fetch_data_between_var_pairs($TMPL->tagdata, 'months');
						$var_pair_output	= '';

						foreach($data AS $res)
						{
							// merge $res with $row
							$res = array_merge($row, $res);
			
							// append fresh template to output
							$var_pair_output .= $var_pair_template;
							
							// handle cond vars in var pair
							$var_pair_output = $FNS->prep_conditionals($var_pair_output, $res);
								
							// handle single vars in var pair
							foreach($res AS $k => $v)
							{
								$var_pair_output = $TMPL->swap_var_single($k, $v, $var_pair_output);
							}
						}

						// do the backspace thing, if necessary
						$bksp = (is_array($val) && isset($val['backspace'])) ? $val['backspace'] : 0;
						if ($bksp)
						{
							$var_pair_output = substr(rtrim($var_pair_output), 0, - $bksp);
						}

						// replace var pair with stuff
						$tagdata = preg_replace("/".LD.'months'.".*?".RD."(.*?)".LD.SLASH.'months'.RD."/s", $var_pair_output, $tagdata, 1);
					}
					// got nothing, empty the lot
					else
					{
						$tagdata = $TMPL->delete_var_pairs($key, 'months', $tagdata);
					}
				}
			} // END VAR PAIRS
			
			$this->return_data .= $tagdata;
		
		} // end foreach()
	
	}
	// END constructor
	
	
	// ----------------------------------------
	//	Plugin Usage
	// ----------------------------------------

	// This function describes how the plugin is used.

	function usage()
	{
		ob_start(); 
		?>
			Parameters:
			- weblog="blog|news"
			- status="not closed"
			- category="15|16"
			- show_expired="yes"
			- show_future_entries="yes"
			- sort="asc"
			- monthsort="desc"
			- start_month="1" (defaults to the month of the oldest entry)
			- end_month="12" (defaults to the month of the newest entry)
			- start_year="2000" (defaults to the year of the oldest entry)
			- end_year="2010" (defaults to the year of the newest entry)

			Tag pairs:
			- {months backspace="1"}{/months}

			Single tags:
			- {year}
			- {year_short}
			- {year_count}
			- {total_years}
			- {leap_year}
			- {total_months}
			- {month} *
			- {month_num} *
			- {month_short} *
			- {month_num_short} *
			- {month_count} *
			- {num_entries} *
			- {num_entries_percentage} *
			- {num_entries_percentage_rounded} *

			Tags marked with * are available in between the {months} tag pair only.

			Example:

			{exp:yearly_archives weblog="blog" start_month="1" status="not closed" sort="desc"}
			{if year_count == 1}<ul>{/if}
				<li>
					{year}
					<ul>
					{months}
						<li>
						{if num_entries != 0}
							<a href="{path=blog/archive}{year}/{month_num}/" title="{num_entries} entries in {month} {year}">{month_short}</a>
						{if:else}
							{month_short}
						{/if}
						</li>
					{/months}
					</ul>
				</li>
			{if year_count == total_years}</ul>{/if}
			{/exp:yearly_archives}
		<?php
		$buffer = ob_get_contents();
	
		ob_end_clean(); 

		return $buffer;
	}
	// END

}
// END CLASS
?>