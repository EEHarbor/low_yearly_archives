<?php

final class PluginTest extends LowYearlyTestCase
{
    /**
     * Load the shipped plugin through its real archived parent class.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        require_once PATH_THIRD . 'low_yearly_archives/pi.low_yearly_archives.php';
    }

    /**
     * Return the template's no-results output before trying to parse dates.
     *
     * @return void
     */
    public function testNoEntriesReturnsNoResults(): void
    {
        $plugin = new Low_yearly_archives();

        self::assertSame('no archive entries', $plugin->return_data);
        self::assertSame(1, $this->ee->TMPL->noResultsCalls);
        self::assertSame(array(), $this->ee->TMPL->parsed);
        self::assertSame('4.1.1', $plugin->version);
    }

    /**
     * Build ascending years, gaps, percentages, counts and translated months.
     *
     * @return void
     */
    public function testAscendingArchiveFillsGapsAndPreservesAnnualCounts(): void
    {
        $this->ee->db->rows = array(
            array('ym' => '202002', 'num_entries' => '2'),
            array('ym' => '202010', 'num_entries' => '1'),
            array('ym' => '202212', 'num_entries' => '4'),
        );
        $this->ee->TMPL->tagparams = array('sort' => 'asc');

        $plugin = new Low_yearly_archives();
        $years = $this->ee->TMPL->parsed[0][1];

        self::assertSame('rendered archive', $plugin->return_data);
        self::assertSame($this->ee->TMPL->tagdata, $this->ee->TMPL->parsed[0][0]);
        self::assertSame(array(2020, 2021, 2022), array_column($years, 'year'));
        self::assertSame(array('20', '21', '22'), array_column($years, 'year_short'));
        self::assertSame(array(1, 2, 3), array_column($years, 'year_count'));
        self::assertSame(array(3, 3, 3), array_column($years, 'total_years'));
        self::assertSame(array(3, 0, 4), array_column($years, 'entries_in_year'));
        self::assertSame(array('1', '0', '0'), array_column($years, 'leap_year'));
        self::assertSame(array(11, 12, 12), array_column($years, 'total_months'));
        self::assertSame(array('02', '03', '04', '05', '06', '07', '08', '09', 10, 11, 12), array_column($years[0]['months'], 'month_num'));
        self::assertSame(array(
            'month' => 'translated:February', 'month_num' => '02',
            'month_short' => 'translated:Feb', 'month_num_short' => 2,
            'month_count' => 1, 'num_entries' => '2',
            'num_entries_percentage' => 2 / 3 * 100,
            'num_entries_percentage_rounded' => 67.0,
        ), $years[0]['months'][0]);
        self::assertSame(0, $years[1]['months'][0]['num_entries']);
        self::assertSame(0, $years[1]['months'][0]['num_entries_percentage']);
        self::assertSame('translated:May_l', $years[1]['months'][4]['month']);
        self::assertSame(100, $years[2]['months'][11]['num_entries_percentage']);
    }

    /**
     * Respect explicit bounds, descending year/month sort and a zero-entry year.
     *
     * @return void
     */
    public function testDescendingArchiveHonorsExplicitBounds(): void
    {
        $this->ee->db->rows = array(array('ym' => '202002', 'num_entries' => '3'));
        $this->ee->TMPL->tagparams = array(
            'start_year' => '2019', 'end_year' => '2020',
            'start_month' => '11', 'end_month' => '02', 'monthsort' => 'desc',
        );

        new Low_yearly_archives();
        $years = $this->ee->TMPL->parsed[0][1];

        self::assertSame(array(2020, 2019), array_column($years, 'year'));
        self::assertSame(array('02', '01'), array_column($years[0]['months'], 'month_num'));
        self::assertSame(array(12, 11), array_column($years[1]['months'], 'month_num'));
        self::assertSame(array(1, 2), array_column($years[0]['months'], 'month_count'));
        self::assertSame(0, $years[1]['entries_in_year']);
    }

    /**
     * Fall back to full calendar boundaries for out-of-range month parameters.
     *
     * @param string $startMonth
     * @param string $endMonth
     * @return void
     * @dataProvider invalidMonthBounds
     */
    public function testOutOfRangeMonthBoundsUseCalendarYear($startMonth, $endMonth): void
    {
        $this->ee->db->rows = array(array('ym' => '202002', 'num_entries' => '1'));
        $this->ee->TMPL->tagparams = array('start_month' => $startMonth, 'end_month' => $endMonth);

        new Low_yearly_archives();
        $year = $this->ee->TMPL->parsed[0][1][0];

        self::assertCount(12, $year['months']);
        self::assertSame('01', $year['months'][0]['month_num']);
        self::assertSame(12, $year['months'][11]['month_num']);
    }

    /**
     * Supply both sides of each month bound comparison.
     *
     * @return array
     */
    public static function invalidMonthBounds(): array
    {
        return array('below range' => array('-1', '-1'), 'above range' => array('13', '13'));
    }

    /**
     * Verify the complete default Active Record query and ordering.
     *
     * @return void
     */
    public function testDefaultQueryRestrictsSitesStatusExpirationAndFutureDates(): void
    {
        $this->invoke($this->unconstructedPlugin(), '_get_entries');

        self::assertSame(array(
            array('select', array(array('CONCAT(t.year,t.month) AS ym', 'COUNT(*) AS num_entries'))),
            array('from', array('channel_titles t')),
            array('where_in', array('t.site_id', array(1, 3))),
            array('group_by', array('ym')),
            array('order_by', array('ym', 'asc')),
            array('where_in', array('t.status', array('open'))),
            array('where', array("(t.expiration_date = '0' OR t.expiration_date > '1705320000')")),
            array('where', array("t.entry_date < '1705320000'")),
            array('get', array()),
        ), $this->ee->db->calls);
    }

    /**
     * Apply include/exclude filters and normalized yes flags without raw SQL.
     *
     * @param array $parameters
     * @param array $expected
     * @return void
     * @dataProvider queryFilters
     */
    public function testQueryFilterShapes(array $parameters, array $expected): void
    {
        $this->ee->TMPL->tagparams = $parameters + array('status' => '', 'show_expired' => 'on', 'show_future_entries' => 'y');
        $this->ee->db->rows = array(array('ym' => '202401', 'num_entries' => '1'));

        self::assertSame($this->ee->db->rows, $this->invoke($this->unconstructedPlugin(), '_get_entries'));
        self::assertSame(array_merge(array(
            array('select', array(array('CONCAT(t.year,t.month) AS ym', 'COUNT(*) AS num_entries'))),
            array('from', array('channel_titles t')),
            array('where_in', array('t.site_id', array(1, 3))),
            array('group_by', array('ym')),
            array('order_by', array('ym', 'asc')),
        ), $expected, array(array('get', array()))), $this->ee->db->calls);
    }

    /**
     * Cover included/excluded channels, statuses, authors and category unions.
     *
     * @return array
     */
    public static function queryFilters(): array
    {
        return array(
            'included filters' => array(
                array('channel' => 'news|blog', 'status' => 'open|featured', 'author_id' => '2|3', 'category' => '4|5'),
                array(
                    array('join', array('channels c', 't.channel_id = c.channel_id')),
                    array('where_in', array('c.channel_name', array('news', 'blog'))),
                    array('where_in', array('t.status', array('open', 'featured'))),
                    array('where_in', array('t.author_id', array('2', '3'))),
                    array('join', array('category_posts cp', 'cp.entry_id = t.entry_id')),
                    array('where_in', array('cp.cat_id', array('4', '5'))),
                ),
            ),
            'excluded filters' => array(
                array('channel' => 'NOT news', 'status' => 'not closed', 'author_id' => 'not 2|3', 'category' => 'not 4|5'),
                array(
                    array('join', array('channels c', 't.channel_id = c.channel_id')),
                    array('where_not_in', array('c.channel_name', array('news'))),
                    array('where_not_in', array('t.status', array('closed'))),
                    array('where_not_in', array('t.author_id', array('2', '3'))),
                    array('join', array('category_posts cp', 'cp.entry_id = t.entry_id')),
                    array('where_not_in', array('cp.cat_id', array('4', '5'))),
                ),
            ),
            'current member' => array(array('author_id' => 'CURRENT_USER'), array(array('where_in', array('t.author_id', array('42'))))),
            'other members' => array(array('author_id' => 'NOT_CURRENT_USER'), array(array('where_not_in', array('t.author_id', array('42'))))),
            'zero filter' => array(array('channel' => '0', 'author_id' => '0', 'category' => '0'), array()),
        );
    }

    /**
     * Intersect category assignments and force a no-match constraint when empty.
     *
     * @param array $categoryRows
     * @param array $entryIds
     * @return void
     * @dataProvider categoryIntersections
     */
    public function testCategoryIntersection(array $categoryRows, array $entryIds): void
    {
        $this->ee->TMPL->tagparams = array('category' => '4&5');
        $this->ee->db->queryRows = $categoryRows;

        $this->invoke($this->unconstructedPlugin(), '_get_entries');

        self::assertContains(array('where_in', array('t.entry_id', $entryIds)), $this->ee->db->calls);
        $sql = $this->ee->db->calls[8][1][0];
        self::assertStringContainsString('WHERE cat_id IN (4,5)', $sql);
        self::assertStringContainsString('GROUP BY entry_id HAVING num = 2', $sql);
        self::assertCount(11, $this->ee->db->calls);
    }

    /**
     * Provide matching and empty category results.
     *
     * @return array
     */
    public static function categoryIntersections(): array
    {
        return array(array(array(array('entry_id' => '7'), array('entry_id' => '9')), array('7', '9')), array(array(), array(0)));
    }

    /**
     * Split EE parameter delimiters without losing false-like string values.
     *
     * @param mixed $parameter
     * @param array $expected
     * @return void
     * @dataProvider parameterShapes
     */
    public function testExplodeParameter($parameter, array $expected): void
    {
        self::assertSame($expected, $this->invoke($this->unconstructedPlugin(), '_explode_param', array($parameter)));
    }

    /**
     * Supply template include/exclude syntax and current-member IDs.
     *
     * @return array
     */
    public static function parameterShapes(): array
    {
        return array(
            array('not 1|2', array(array('1', '2'), false)),
            array('NOT 4&&5&6|7', array(array('4', '5', '6', '7'), false)),
            array('not ', array(array(''), false)),
            array('notable', array(array('notable'), true)),
            array('0', array(array('0'), true)),
            array('', array(array(''), true)),
            array(42, array(array('42'), true)),
            array('|1||', array(array('', '1', '', ''), true)),
        );
    }

    /**
     * Build a plugin instance when testing helpers that do not use constructor state.
     *
     * @return Low_yearly_archives
     */
    private function unconstructedPlugin()
    {
        return (new ReflectionClass(Low_yearly_archives::class))->newInstanceWithoutConstructor();
    }
}
