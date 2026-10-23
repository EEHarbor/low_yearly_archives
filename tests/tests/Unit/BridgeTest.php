<?php

use Low\YearlyArchives\FluxCapacitor\Conduit\StaticCache;

final class BridgeTest extends LowYearlyTestCase
{
    /**
     * Verify modern CP URLs keep the expected route and query variables.
     *
     * @param string $class
     * @return void
     * @dataProvider modernBridges
     */
    public function testModernUrls($class): void
    {
        $bridge = new $class(array('module' => 'low_yearly_archives', 'module_name' => 'Low Yearly Archives'));
        self::assertSame('addons/settings/low_yearly_archives', $bridge->getBaseURL('/')->path);
        self::assertSame('addons/settings/low_yearly_archives', $bridge->getBaseURL()->path);
        self::assertSame('addons/settings/low_yearly_archives/license/extra', $bridge->getBaseURL('license', '/extra')->path);
        self::assertSame('publish/create/4', $bridge->cpURL('publish', 'create', array('channel_id' => 4))->path);
        self::assertSame('publish/edit/entry/9', $bridge->cpURL('listing', 'edit', array('entry_id' => 9))->path);
        self::assertSame('publish', $bridge->cpURL('publish')->path);
        self::assertSame('members', $bridge->cpURL('members')->path);
        self::assertSame(array('q' => 'x'), $bridge->cpURL('publish', 'create', array('q' => 'x'))->variables);
        self::assertSame('addons/settings/low_yearly_archives/license', $bridge->moduleURL('license', array('site' => 3))->path);
        self::assertSame(array('site' => 3), $bridge->moduleURL('license', array('site' => 3))->variables);
    }

    /**
     * Verify pagination uses page inputs, offsets and optional rendering settings.
     *
     * @param string $class
     * @return void
     * @dataProvider modernBridges
     */
    public function testModernPagination($class): void
    {
        $bridge = $this->bridge($class);
        self::assertSame(1, $bridge->getCurrentPage());
        $this->ee->input->values = array('page' => '3');
        self::assertSame('3', $bridge->getCurrentPage());
        $this->ee->input->values['per_page'] = '2';
        self::assertSame('2', $bridge->getCurrentPage());
        self::assertSame(20, $bridge->getStartNum(array('current_page' => 3, 'per_page' => 10)));
        $pagination = $this->getMockBuilder(stdClass::class)->addMethods(array('perPage', 'currentPage', 'displayPageLinks', 'queryStringVariable', 'render'))->getMock();
        $pagination->method('perPage')->willReturnSelf();
        $pagination->method('currentPage')->willReturnSelf();
        $pagination->expects(self::once())->method('displayPageLinks')->with(7)->willReturnSelf();
        $pagination->expects(self::once())->method('queryStringVariable')->with('offset')->willReturnSelf();
        $pagination->expects(self::exactly(2))->method('render')->with('/archive')->willReturn('pagination links');
        $this->ee->services['CP/Pagination'] = $pagination;
        $options = array('total_rows' => 42, 'per_page' => 10, 'current_page' => 3, 'base_url' => '/archive');
        self::assertSame('pagination links', $bridge->pagination($options));
        self::assertSame('pagination links', $bridge->pagination($options + array('page_links' => 7, 'page_query_string' => 'offset')));
    }

    /**
     * Keep local and persistent cache namespaces distinct and handle unknown modes.
     *
     * @param string $class
     * @return void
     * @dataProvider modernBridges
     */
    public function testModernCache($class): void
    {
        $bridge = $this->bridge($class);
        $this->ee->cache = new LowYearlySpy(array('get' => array('cached'), 'save' => true, 'delete' => true));
        self::assertSame(array('cached'), $bridge->cache('get', 'key'));
        self::assertTrue($bridge->cache('set', 'key', array('data')));
        self::assertTrue($bridge->cache('delete', 'key'));
        self::assertTrue($bridge->cache('clear', 'key'));
        self::assertFalse($bridge->cache('unknown'));
        self::assertSame('value', $bridge->cache('set', 'key', 'value', false));
        self::assertSame('value', $bridge->cache('get', 'key', false, false));
        self::assertSame(array(
            array('get', array('/low_yearly_archives/key')),
            array('save', array('/low_yearly_archives/key', array('data'))),
            array('delete', array('/low_yearly_archives/key')), array('delete', array('/low_yearly_archives/key')),
        ), $this->ee->cache->calls);
    }

    /**
     * Merge legacy field settings and database settings beneath package metadata.
     *
     * @param string $class
     * @return void
     * @dataProvider allBridges
     */
    public function testSettingsMergeAndCache($class): void
    {
        $bridge = $this->bridge($class);
        foreach (array($this->ee->db, $this->ee->settings_db) as $database) {
            $database->tableExists = true;
            $database->data_cache['table_names'] = array('stale');
            $database->tableRows = array(
                'low_yearly_archives_settings' => array(array('database' => 'yes', 'shared' => 'db', 'version' => 'wrong')),
                'fieldtypes' => array(array('settings' => base64_encode(serialize(array('fieldtype' => 'yes', 'shared' => 'ft'))))),
            );
        }

        $settings = $bridge->getSettings(true);
        self::assertSame('yes', $settings['database']);
        self::assertSame('yes', $settings['fieldtype']);
        self::assertSame('db', $settings['shared']);
        self::assertSame('4.1.1', $settings['version']);
        self::assertSame($settings, (array) $bridge->getSettings());
        self::assertSame('4.1.1', $bridge->getConfig('version'));
        self::assertSame($settings, StaticCache::get('app_settings'));
    }

    /**
     * Persist setting updates with the current site and keep the request cache fresh.
     *
     * @param string $class
     * @return void
     * @dataProvider modernBridges
     */
    public function testSetConfigInsertsUpdatesOrUsesRequestCache($class): void
    {
        $bridge = $this->bridge($class);
        self::assertSame('4.1.1', $bridge->getConfig('version'));
        $this->ee->settings_db->calls = array();
        $bridge->setConfig('limit', 0);
        self::assertSame(0, $bridge->getConfig('limit'));
        self::assertSame(array(array('table_exists', array('low_yearly_archives_settings'))), $this->ee->settings_db->calls);
        $this->ee->settings_db->tableExists = true;
        $this->ee->settings_db->data_cache['table_names'] = array('stale');
        $bridge->setConfig('limit', 3);
        self::assertContains(array('insert', array('low_yearly_archives_settings', array('site_id' => 1, 'limit' => 3))), $this->ee->settings_db->calls);
        $this->ee->settings_db->count = 1;
        $bridge->setConfig('limit', 4);
        self::assertContains(array('update', array('low_yearly_archives_settings', array('site_id' => 1, 'limit' => 4))), $this->ee->settings_db->calls);
        self::assertArrayNotHasKey('table_names', $this->ee->settings_db->data_cache);
        self::assertSame(4, StaticCache::get('app_settings')['limit']);
    }

    /**
     * Treat serialized false settings as empty while retaining add-on metadata.
     *
     * @param string $class
     * @return void
     * @dataProvider allBridges
     */
    public function testEmptySerializedSettings($class): void
    {
        $bridge = $this->bridge($class);
        $this->ee->db->rows = $this->ee->settings_db->rows = array(array('settings' => base64_encode(serialize(false))));

        self::assertSame('4.1.1', $bridge->getConfig('version'));
    }

    /**
     * Map flash severities and optional alert methods with deferred display.
     *
     * @param string $class
     * @return void
     * @dataProvider modernBridges
     */
    public function testModernFlashAlerts($class): void
    {
        $bridge = $this->bridge($class);
        foreach (array('message_error' => 'asIssue', 'message_failure' => 'asIssue', 'message_notice' => 'asWarning', 'message_success' => 'asSuccess') as $type => $method) {
            $this->ee->alert->calls = array();
            $bridge->flashData($type, 'Title', 'Body', array('cannotClose', 'doesNotExist'));
            self::assertSame(array(
                array(substr($class, -1) === '3' ? 'make' : 'makeInline', array('Title')), array($method, array()), array('withTitle', array('Title')),
                array('addToBody', array('Body')), array('canClose', array()), array('cannotClose', array()), array('defer', array()),
            ), $this->ee->alert->calls);
        }
    }

    /**
     * Return URI components and configured/fallback cache paths consistently.
     *
     * @param string $class
     * @return void
     * @dataProvider modernBridges
     */
    public function testModernRequestContext($class): void
    {
        $bridge = $this->bridge($class);
        self::assertSame('7', $bridge->getEEVersion());
        self::assertSame(APP_VER, $bridge->getEEVersion(false));
        self::assertTrue($bridge->ver_gt('6'));
        self::assertTrue($bridge->ver_gte('7'));
        self::assertTrue($bridge->ver_lt('8'));
        self::assertTrue($bridge->ver_lte('7'));
        self::assertFalse($bridge->is_ee2());
        self::assertFalse($bridge->is_ee3());
        self::assertFalse($bridge->is_ee4());
        self::assertFalse($bridge->is_ee5());
        self::assertSame('index', $bridge->getCurrentUrlInfo('method'));
        self::assertSame('low_yearly_archives', $bridge->getCurrentUrlInfo('module'));
        self::assertTrue($bridge->getCurrentUrlInfo()['cp']);
        self::assertIsArray($bridge->getCurrentUrlInfo('unknown'));
        self::assertSame(PATH_CACHE, $bridge->getCachePath());
        $this->ee->config->values['cache_path'] = '/custom/cache/';
        self::assertSame('/custom/cache/', $bridge->getCachePath());
        $this->ee->config->values['theme_folder_url'] = 'https://example.test/themes/';
        self::assertSame('https://example.test/themes/user/low_yearly_archives/', $bridge->getAddonThemesDir());
    }

    /**
     * Handle short frontend routes without hiding an undefined segment diagnostic.
     *
     * @param string $class
     * @return void
     * @dataProvider modernBridges
     */
    public function testShortFrontendUriDoesNotEmitSourceDiagnostics($class): void
    {
        $this->ee->uri->path = 'front/page';

        self::assertSame('index', $this->bridge($class)->getCurrentUrlInfo('method'));
    }

    /**
     * Supply all modern compatibility classes, including the EE6 inherited bridge.
     *
     * @return array
     */
    public static function modernBridges(): array
    {
        return array_map(function ($version) { return array('Low\\YearlyArchives\\FluxCapacitor\\Bridge\\EE' . $version); }, array(3, 4, 5, 6));
    }

    /**
     * Supply every concrete bridge for shared settings contracts.
     *
     * @return array
     */
    public static function allBridges(): array
    {
        return array_merge(array(array('Low\\YearlyArchives\\FluxCapacitor\\Bridge\\EE2')), self::modernBridges());
    }

    /**
     * Construct the shipped bridge with its supported module information.
     *
     * @param string $class
     * @return object
     */
    private function bridge($class)
    {
        return new $class(array('module' => 'low_yearly_archives', 'module_name' => 'Low Yearly Archives'));
    }
}
