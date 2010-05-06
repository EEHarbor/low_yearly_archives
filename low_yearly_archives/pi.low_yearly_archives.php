<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

// Include config file
require PATH_THIRD.'low_yearly_archives/config.php';

$plugin_info = array(
	'pi_name'			=> $config['name'],
	'pi_version'		=> $config['version'],
	'pi_author'			=> 'Lodewijk Schutte ~ Low',
	'pi_author_url'		=> 'http://loweblog.com/software/low-yearly-archives/',
	'pi_description'	=> 'For displaying yearly archives',
	'pi_usage'			=> Low_yearly_archives::usage()
);

/**
* Low Yearly Archives Plugin Class
*
* @package			low-title-ee2_addon
* @version			2.2
* @author			Lodewijk Schutte ~ Low <low@loweblog.com>
* @link				http://loweblog.com/software/low-yearly-archives/
* @license			http://creativecommons.org/licenses/by-sa/3.0/
*/

class Low_yearly_archives {

	/**
	* Plugin return data
	*
	* @var	string
	*/
	var $return_data;
	
	// --------------------------------------------------------------------

	/**
	* PHP4 Constructor
	*
	* @see	__construct()
	*/
	function Low_yearly_archives()
	{
		$this->__construct();
	}

	// --------------------------------------------------------------------

	/**
	* PHP5 Constructor
	*/
	function __construct()
	{
		/** -------------------------------------
		/**  Get global instance
		/** -------------------------------------*/

		$this->EE =& get_instance();
		
		/** -------------------------------------
		/**  Get some params
		/** -------------------------------------*/
		
		$status	= $this->EE->TMPL->fetch_param('status', 'open');
		$channel= $this->EE->TMPL->fetch_param('channel', '');
		$site_id= $this->EE->TMPL->fetch_param('site_id', $this->EE->config->item('site_id'));
		
		/** -------------------------------------
		/**  Get timeframe params
		/** -------------------------------------*/

		$timeframe = array(
			'start_year'	=> $this->EE->TMPL->fetch_param('start_year'),
			'end_year'		=> $this->EE->TMPL->fetch_param('end_year'),
			'start_month'	=> $this->EE->TMPL->fetch_param('start_month'),	
			'end_month'		=> $this->EE->TMPL->fetch_param('end_month')
		);
		
		/** -------------------------------------
		/**  Get SQL params
		/** -------------------------------------*/
		
		$sql_now	 = $this->EE->db->escape_str( (string) $this->EE->localize->now );
		$sql_expired = ($this->EE->TMPL->fetch_param('show_expired') == 'yes')		? "" : "AND ( expiration_date = 0 OR expiration_date > '{$sql_now}' )";
		$sql_future	 = ($this->EE->TMPL->fetch_param('show_future_entries') == 'yes')	? "" : "AND entry_date < '{$sql_now}'";
		$sql_status	 = $this->EE->functions->sql_andor_string($status, 'status');
		$sql_channel = $this->EE->functions->sql_andor_string($channel, 'channel_name');
		$sql_site_id = $this->EE->db->escape_str($site_id);
		
		/** -------------------------------------
		/**  Get Category params
		/** -------------------------------------*/

		if ($category = $this->EE->TMPL->fetch_param('category'))
		{
			$sql_cat_join = "INNER JOIN exp_category_posts p ON t.entry_id = p.entry_id";
			$sql_category = $this->EE->functions->sql_andor_string($category, 'p.cat_id');
		}
		else
		{
			$sql_cat_join = $sql_category = '';
		}

		/** -------------------------------------
		/**  Compose query
		/** -------------------------------------*/

		$sql = "SELECT
				CONCAT(t.year,t.month) AS ym,
				COUNT(*) AS num_entries
			FROM
				exp_channel_titles t
			INNER JOIN
				exp_channels w
			ON
				t.channel_id = w.channel_id
				{$sql_cat_join}
			WHERE
				w.site_id = '{$sql_site_id}'
				{$sql_category}
				{$sql_expired}
				{$sql_future}
				{$sql_status}
				{$sql_channel}
			GROUP BY
				ym
			ORDER BY
				ym ASC
		";
		$query = $this->EE->db->query($sql);
		
		/** -------------------------------------
		/**  No results? bail.
		/** -------------------------------------*/
		
		if ($query->num_rows() == 0)
		{
			return;
		}

		/** -------------------------------------
		/**  loop thru results and drop 'em like it's hot
		/** -------------------------------------*/

		$years = $months = $result = array();

		foreach ($query->result_array() AS $row)
		{
			// array with 'yearmonth' => 'number_of_entries_in_month'
			$months[$row['ym']] = $row['num_entries'];
			
			// get year, add number of entries to total amount per year
			$tmp_year = substr($row['ym'],0,4);
			if (!isset($years[$tmp_year])) { $years[$tmp_year] = 0; }
			$years[$tmp_year] += $row['num_entries'];
		}
		
		/** -------------------------------------
		/**  Get first and last months, set timeframe
		/** -------------------------------------*/

		$first = key($months);	// get first key = first month
		end($months);			// go to the last element in array
		$last  = key($months);	// and get that key too

		if (!$timeframe['start_year'])	{ $timeframe['start_year']	= substr($first, 0, 4);	}
		if (!$timeframe['end_year'])	{ $timeframe['end_year']	= substr($last,	0, 4);	}
		if (!$timeframe['start_month'])	{ $timeframe['start_month']	= substr($first, -2);	}
		if (!$timeframe['end_month'])	{ $timeframe['end_month']	= substr($last,	-2);	}

		/** -------------------------------------
		/**  ASC or DESC, to put in range() function
		/**  Notice how we don't use anymore queries
		/** -------------------------------------*/

		if ($this->EE->TMPL->fetch_param('sort') == 'asc')
		{
			// if ASC, we start with start, and end with end.
			$year_start = 'start_year';
			$year_end = 'end_year';
		}
		else
		{
			// if DESC, we start with end, and end with start. Get it? :)
			$year_start = 'end_year';
			$year_end = 'start_year';
		}

		/** -------------------------------------
		/**  Compose nice little nested array with all the results - loop thru years first
		/** -------------------------------------*/
		
		// Get array of years to loop through
		$loop_years = range( intval($timeframe[$year_start]), intval($timeframe[$year_end]) );
		
		// Set some vars 
		$total_years = count($loop_years);
		$year_count = 0;
		
		foreach ($loop_years AS $year)
		{
			// init result entry
			$row = array(
				'year'				=> $year,
				'year_short'		=> substr($year, -2),
				'total_years'		=> $total_years,
				'year_count'		=> ++$year_count,
				'entries_in_year'	=> (isset($years[$year]) ? $years[$year] : 0),
				'leap_year'			=> date("L", strtotime("{$year}-01-01 12:00:00")), // leap_year: 1 or 0
				'months'			=> array()
			);
			
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
			if ($this->EE->TMPL->fetch_param('monthsort') == 'desc')
			{
				$monthstart	= 'me';
				$monthend	= 'ms';
			}
			else
			{
				$monthstart	= 'ms';
				$monthend	= 'me';
			}
			
			// get months for this year
			$months_loop = range( intval($$monthstart), intval($$monthend) );
			
			// init month count
			$row['total_months'] = count($months_loop);
			$month_count = 0;

			// then loop thru the months of current year and put vars in result array
			foreach ($months_loop AS $month)
			{
				// leading zero...
				if (strlen($month) == 1) { $month = '0'.$month; }
				
				// nice month names
				$tmp_month = $this->EE->localize->localize_month($month);
				
				// result array
				$data = array(
					'month'				=> $this->EE->lang->line($tmp_month[1]),
					'month_num'			=> $month,
					'month_short'		=> $this->EE->lang->line($tmp_month[0]),
					'month_num_short'	=> intval($month),
					'month_count'		=> ++$month_count,
					'num_entries'		=> ((isset($months[$year.$month])) ? $months[$year.$month] : 0), // got entries for current month?
					// Courtesy of Leevi Graham
					'num_entries_percentage' => 0,
					'num_entries_percentage_rounded' => 0
				);
				
				if ($data['num_entries'] > 0)
				{
					$data['num_entries_percentage'] = $data['num_entries'] / $row['entries_in_year'] * 100;
					$data['num_entries_percentage_rounded'] = round($data['num_entries_percentage']);
				}
				
				$row['months'][] = $data;
			}
			
			$result[] = $row;
			
		} // done looping thru years
		
		/** -------------------------------------
		/**  Boom. Parse variables.
		/** -------------------------------------*/
	
		$this->return_data = $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $result);
	
		//return $this->return_data;
	
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
			- channel="blog|news"
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

			{exp:low_yearly_archives channel="blog" start_month="1" status="not closed" sort="desc"}
			{if "{year_count}" == "1"}<ul>{/if}
				<li>
					{year}
					<ul>
					{months}
						<li>
						{if "{num_entries}" != "0"}
							<a href="{path="blog/archive/{year}/{month_num}"}" title="{num_entries} entries in {month} {year}">{month_short}</a>
						{if:else}
							{month_short}
						{/if}
						</li>
					{/months}
					</ul>
				</li>
			{if "{year_count}" == "{total_years}"}</ul>{/if}
			{/exp:low_yearly_archives}
		<?php
		$buffer = ob_get_contents();
	
		ob_end_clean(); 

		return $buffer;
	}
	// END

}
// END CLASS
?>