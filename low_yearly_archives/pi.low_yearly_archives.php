<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Low Yearly Archives Plugin Class
 *
 * @package        low_yearly_archives
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-yearly-archives
 * @license        http://creativecommons.org/licenses/by-sa/3.0/
 */
class Low_yearly_archives {

	// --------------------------------------------------------------------
	// PROPERTIES
	// --------------------------------------------------------------------

	/**
	 * Plugin return data
	 */
	public $return_data;

	// --------------------------------------------------------------------
	// METHODS
	// --------------------------------------------------------------------

	/**
	 * Constructor
	 */
	public function __construct()
	{
		// -------------------------------------
		// Get the entries. No results? bail.
		// -------------------------------------

		if ( ! ($entries = $this->_get_entries()))
		{
			$this->return_data = ee()->TMPL->no_results();
			return;
		}

		// -------------------------------------
		// Get timeframe params
		// -------------------------------------

		$timeframe = array(
			'start_year'	=> ee()->TMPL->fetch_param('start_year'),
			'end_year'		=> ee()->TMPL->fetch_param('end_year'),
			'start_month'	=> ee()->TMPL->fetch_param('start_month'),
			'end_month'		=> ee()->TMPL->fetch_param('end_month')
		);

		// -------------------------------------
		// loop thru results and drop 'em like it's hot
		// -------------------------------------

		$years = $result = array();

		// array with 'yearmonth' => 'number_of_entries_in_month'
		$months = $this->_flatten_results($entries, 'num_entries', 'ym');

		foreach ($months AS $key => $val)
		{
			// Get the year
			$year = substr($key, 0, 4);

			// Initiate the count if not there
			$years[$year] = $val + (int) @$years[$year];
		}

		// Back to top, clean up
		reset($months);
		unset($entries);

		// -------------------------------------
		// Get first and last months, set timeframe
		// -------------------------------------

		$first = key($months);	// get first key = first month
		end($months);			// go to the last element in array
		$last  = key($months);	// and get that key too

		if (!$timeframe['start_year'])	{ $timeframe['start_year']	= substr($first, 0, 4);	}
		if (!$timeframe['end_year'])	{ $timeframe['end_year']	= substr($last,	0, 4);	}
		if (!$timeframe['start_month'])	{ $timeframe['start_month']	= substr($first, -2);	}
		if (!$timeframe['end_month'])	{ $timeframe['end_month']	= substr($last,	-2);	}

		// -------------------------------------
		// ASC or DESC, to put in range() function
		// Notice how we don't use anymore queries
		// -------------------------------------

		if (ee()->TMPL->fetch_param('sort') == 'asc')
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

		// -------------------------------------
		// Compose nice little nested array with all the results - loop thru years first
		// -------------------------------------

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
				'months'			=> array(),
				'months_reverse'    => array(),
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
			if (ee()->TMPL->fetch_param('monthsort') == 'desc')
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
				$tmp_month = ee()->localize->localize_month($month);

				// result array
				$data = array(
					'month'				=> ee()->lang->line($tmp_month[1]),
					'month_num'			=> $month,
					'month_short'		=> ee()->lang->line($tmp_month[0]),
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

				// flip the month_count around for months_reverse
				$data['month_count'] = $row['total_months'] - $data['month_count'] + 1;

				array_unshift($row['months_reverse'], $data);
			}

			$result[] = $row;

		} // done looping thru years

		// -------------------------------------
		// Boom. Parse variables.
		// -------------------------------------

		$this->return_data = ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $result);

	}

	// --------------------------------------------------------------------

	/**
	 * Query DB for entries
	 *
	 * @access     private
	 * @return     array
	 */
	private function _get_entries()
	{
		// -------------------------------------
		// When's this?
		// -------------------------------------

		$now = ee()->localize->now;

		// -------------------------------------
		// Start building query
		// -------------------------------------

		$select = array(
			'CONCAT(t.year,t.month) AS ym',
			'COUNT(*) AS num_entries'
		);

		// Basic query stuff
		ee()->db->select($select)
			->from('channel_titles t')
			->where_in('t.site_id', ee()->TMPL->site_ids)
			->group_by('ym')
			->order_by('ym', 'asc');

		// --------------------------------------
		// Filter by channel
		// --------------------------------------

		if ($channels = ee()->TMPL->fetch_param('channel'))
		{
			// Determine which channels to filter by
			list($channels, $in) = $this->_explode_param($channels);

			// Join channels table
			ee()->db->join('channels c', 't.channel_id = c.channel_id');
			ee()->db->{($in ? 'where_in' : 'where_not_in')}('c.channel_name', $channels);
		}

		// --------------------------------------
		// Filter by status - defaults to open
		// --------------------------------------

		if ($status = ee()->TMPL->fetch_param('status', 'open'))
		{
			// Determine which statuses to filter by
			list($status, $in) = $this->_explode_param($status);

			// Adjust query accordingly
			ee()->db->{($in ? 'where_in' : 'where_not_in')}('t.status', $status);
		}

		// --------------------------------------
		// Filter by expired entries
		// --------------------------------------

		if (ee()->TMPL->fetch_param('show_expired') != 'yes')
		{
			ee()->db->where("(t.expiration_date = '0' OR t.expiration_date > '{$now}')");
		}

		// --------------------------------------
		// Filter by future entries
		// --------------------------------------

		if (ee()->TMPL->fetch_param('show_future_entries') != 'yes')
		{
			ee()->db->where("t.entry_date < '{$now}'");
		}

		// --------------------------------------
		// Filter by author
		// --------------------------------------

		if ($author = ee()->TMPL->fetch_param('author_id'))
		{
			if ($author == 'NOT_CURRENT_USER') $author = 'not '.ee()->session->userdata('member_id');
			if ($author == 'CURRENT_USER') $author = ee()->session->userdata('member_id');

			// Change to proper param
			list($author, $in) = $this->_explode_param($author);

			// Adjust query accordingly
			ee()->db->{($in ? 'where_in' : 'where_not_in')}('t.author_id', $author);
		}

		// --------------------------------------
		// Filter by category
		// --------------------------------------

		if ($categories_param = ee()->TMPL->fetch_param('category'))
		{
			// Determine which categories to filter by
			list($categories, $in) = $this->_explode_param($categories_param);

			if (strpos($categories_param, '&'))
			{
				// Execute query the old-fashioned way, so we don't interfere with active record
				// Get the entry ids that have all given categories assigned
				$query = ee()->db->query(
					"SELECT entry_id, COUNT(*) AS num
					FROM exp_category_posts
					WHERE cat_id IN (".implode(',', $categories).")
					GROUP BY entry_id HAVING num = ". count($categories));

				// If no entries are found, make sure we limit the query accordingly
				if ( ! ($entry_ids = $this->_flatten_results($query->result_array(), 'entry_id')))
				{
					$entry_ids = array(0);
				}

				ee()->db->where_in('t.entry_id', $entry_ids);
			}
			else
			{
				// Join category table
				ee()->db->join('category_posts cp', 'cp.entry_id = t.entry_id');
				ee()->db->{($in ? 'where_in' : 'where_not_in')}('cp.cat_id', $categories);
			}
		}

		// -------------------------------------
		// Get the query
		// -------------------------------------

		$query = ee()->db->get();

		return $query->result_array();
	}

	// --------------------------------------------------------------------

	/**
	 * Converts EE parameter to workable php vars
	 *
	 * @access     private
	 * @param      string    String like 'not 1|2|3' or '40|15|34|234'
	 * @return     array     [0] = array of ids, [1] = boolean whether to include or exclude: TRUE means include, FALSE means exclude
	 */
	private function _explode_param($str)
	{
		// --------------------------------------
		// Initiate $in var to TRUE
		// --------------------------------------

		$in = TRUE;

		// --------------------------------------
		// Check if parameter is "not bla|bla"
		// --------------------------------------

		if (strtolower(substr($str, 0, 4)) == 'not ')
		{
			// Change $in var accordingly
			$in = FALSE;

			// Strip 'not ' from string
			$str = substr($str, 4);
		}

		// --------------------------------------
		// Return two values in an array
		// --------------------------------------

		return array(preg_split('/(&&?|\|)/', $str), $in);
	}

	// --------------------------------------------------------------------

	/**
	 * Flatten results
	 *
	 * Given a DB result set, this will return an (associative) array
	 * based on the keys given
	 *
	 * @param      array
	 * @param      string    key of array to use as value
	 * @param      string    key of array to use as key (optional)
	 * @return     array
	 */
	private function _flatten_results($resultset, $val, $key = FALSE)
	{
		$array = array();

		foreach ($resultset AS $row)
		{
			if ($key !== FALSE)
			{
				$array[$row[$key]] = $row[$val];
			}
			else
			{
				$array[] = $row[$val];
			}
		}

		return $array;
	}

}
// END CLASS
