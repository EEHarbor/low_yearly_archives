<?php

use Low\YearlyArchives\FluxCapacitor\Bridge\EE2;

final class LegacyBridgeTest extends LowYearlyTestCase
{
    /**
     * Preserve legacy collaborator arguments and request-local replacement semantics.
     *
     * @return void
     */
    public function testLegacyCollaboratorAdapters(): void
    {
        $bridge = $this->bridge();
        $this->ee->api = new LowYearlySpy(array('instantiate' => null));
        $this->ee->security = new LowYearlySpy(array('xss_clean' => array('clean')));
        $this->ee->functions = new LowYearlySpy(array('assign_variables' => array('single' => array('title')), 'assign_parameters' => array('limit' => 3)));
        $this->ee->cp = new class {
            public $footer_item = array('existing footer');
            public $calls = array();

            /**
             * Record legacy footer markup without introducing undeclared fixture properties.
             *
             * @param string $html
             * @return void
             */
            public function add_to_foot($html) { $this->calls[] = array('add_to_foot', array($html)); }
        };
        self::assertNull($bridge->instantiate('channel_entries'));
        self::assertSame(array(array('instantiate', array('channel_entries'))), $this->ee->api->calls);
        self::assertSame(array('clean'), $bridge->xss_clean(array('unsafe')));
        self::assertSame(array(array('xss_clean', array(array('unsafe')))), $this->ee->security->calls);
        self::assertSame(array('single' => array('title')), $bridge->assign_variables('{title}'));
        self::assertSame(array('limit' => 3), $bridge->assign_parameters('limit="3"', array('limit' => 5)));
        self::assertSame(array(array('assign_variables', array('{title}')), array('assign_parameters', array('limit="3"', array('limit' => 5)))), $this->ee->functions->calls);
        self::assertSame(array('existing footer'), $bridge->getFooter());
        $bridge->javascript_to_page('window.legacy = true;');
        self::assertSame(array(array('add_to_foot', array('<script type="text/javascript">window.legacy = true;</script>'))), $this->ee->cp->calls);
        $this->ee->custom = 'old';
        $bridge->overwriteEEClass('custom', array('replacement'));
        self::assertSame(array('replacement'), $this->ee->custom);
        $bridge->removeEEClass('custom');
        self::assertSame('', $this->ee->custom);
        $bridge->flashData('message_notice', 'Title', 'Body', array('cannotClose'));
        self::assertSame(array('message_notice' => 'Title Body'), $this->ee->session->flash);
    }

    /**
     * Translate optional pagination settings without dropping the original options.
     *
     * @return void
     */
    public function testPaginationAdapter(): void
    {
        $this->ee->load = new LowYearlySpy(array('library' => null));
        $this->ee->pagination = new LowYearlySpy(array('initialize' => null, 'create_links' => 'legacy links'));
        $bridge = $this->bridge();
        self::assertSame('legacy links', $bridge->pagination(array('current_page' => 3, 'per_page' => 10)));
        self::assertSame('legacy links', $bridge->pagination(array('per_page' => 10)));
        self::assertSame(array(
            array('initialize', array(array('current_page' => 3, 'per_page' => 10, 'cur_page' => 3))),
            array('create_links', array()), array('initialize', array(array('per_page' => 10))), array('create_links', array()),
        ), $this->ee->pagination->calls);
        self::assertSame(array(array('library', array('pagination')), array('library', array('pagination'))), $this->ee->load->calls);
    }

    /**
     * Resolve missing, zero, numeric-string and offset-based legacy page inputs.
     *
     * @param array $input
     * @param int $expected
     * @return void
     * @dataProvider pages
     */
    public function testPageInputs(array $input, $expected): void
    {
        $this->ee->input->values = $input;
        $bridge = $this->bridge();
        self::assertSame($expected, $bridge->getCurrentPage(array('per_page' => 10)));
        self::assertSame(20, $bridge->getStartNum(array('current_page' => 3, 'per_page' => 10)));
    }

    /**
     * Supply the supported legacy input representations and precedence outcomes.
     *
     * @return array
     */
    public static function pages(): array
    {
        return array(array(array(), 1), array(array('page' => '0'), 1), array(array('page' => '3'), 3), array(array('page' => '9', 'per_page' => '20'), 3));
    }

    /**
     * Keep request payloads intact and isolate namespace-wide and single-key invalidation.
     *
     * @return void
     */
    public function testRequestCache(): void
    {
        $bridge = $this->bridge();
        $this->ee->session->cache['other'] = array('key' => 'kept');
        self::assertFalse($bridge->cache('get', 'missing', false, false));
        self::assertSame(0, $bridge->cache('set', 'zero', 0, false));
        self::assertSame(0, $bridge->cache('get', 'zero', false, false));
        self::assertSame(array(), $bridge->cache('set', 'empty', array(), false));
        self::assertSame(array(), $bridge->cache('get', 'empty', false, false));
        $bridge->cache('delete', 'zero', false, false);
        self::assertFalse($bridge->cache('get', 'zero', false, false));
        self::assertSame(array(), $bridge->cache('get', 'empty', false, false));
        $bridge->cache('clear', false, false, false);
        self::assertArrayNotHasKey('low_yearly_archives', $this->ee->session->cache);
        self::assertSame(array('key' => 'kept'), $this->ee->session->cache['other']);
        self::assertFalse($bridge->cache('unknown'));
    }

    /**
     * Forward complete persistent-cache keys and values on post-2.8 EE runtimes.
     *
     * @return void
     */
    public function testPersistentCacheReadAndWrite(): void
    {
        $this->ee->cache = new LowYearlySpy(array('get' => array('cached'), 'save' => true));
        $bridge = $this->bridge();
        self::assertSame(array('cached'), $bridge->cache('get', 'key'));
        self::assertTrue($bridge->cache('set', 'key', array('value')));
        self::assertSame(array(array('get', array('/low_yearly_archives/key')), array('save', array('/low_yearly_archives/key', array('value')))), $this->ee->cache->calls);
    }

    /**
     * Invalidate the persistent entry through the same storage boundary used to write it.
     *
     * @return void
     */
    public function testPersistentCacheDeletion(): void
    {
        $this->ee->cache = new LowYearlySpy(array('delete' => true));
        $this->bridge()->cache('delete', 'key');

        self::assertSame(array(array('delete', array('/low_yearly_archives/key'))), $this->ee->cache->calls);
    }

    /**
     * Read legacy query context and fallback paths without leaking request globals.
     *
     * @return void
     */
    public function testRequestContext(): void
    {
        $previousGet = $_GET;
        $hadQuery = array_key_exists('QUERY_STRING', $_SERVER);
        $previousQuery = $_SERVER['QUERY_STRING'] ?? null;
        try {
            $_SERVER['QUERY_STRING'] = '/cp/addons/low_yearly_archives/';
            $_GET = array('module' => 'low_yearly_archives', 'method' => 'license');
            $bridge = $this->bridge();
            self::assertSame('license', $bridge->getCurrentUrlInfo('method'));
            self::assertSame('low_yearly_archives', $bridge->getCurrentUrlInfo('module'));
            self::assertTrue($bridge->getCurrentUrlInfo()['cp']);
            self::assertSame(array('cp', 'addons', 'low_yearly_archives'), $bridge->getCurrentUrlInfo()['segments']);
            self::assertIsArray($bridge->getCurrentUrlInfo('unknown'));
            unset($_GET['method']);
            self::assertSame('index', $bridge->getCurrentUrlInfo('method'));
            self::assertSame(APPPATH . 'cache/', $bridge->getCachePath());
            $this->ee->config->values['cache_path'] = '/custom/cache';
            self::assertSame('/custom/cache', $bridge->getCachePath());
            $this->ee->config->values['theme_folder_url'] = 'https://example.test/themes/';
            self::assertSame('https://example.test/themes/third_party/low_yearly_archives/', $bridge->getAddonThemesDir());
        } finally {
            $_GET = $previousGet;
            if ($hadQuery) {
                $_SERVER['QUERY_STRING'] = $previousQuery;
            }
            if (!$hadQuery) {
                unset($_SERVER['QUERY_STRING']);
            }
        }
    }

    /**
     * Use a cloned database query when checking installed modules.
     *
     * @return void
     */
    public function testInstalledModules(): void
    {
        $bridge = $this->bridge();
        self::assertFalse($bridge->module_installed('Channel'));
        $this->ee->db->count = 1;
        self::assertTrue($bridge->module_installed('Channel'));
        self::assertSame(array(), $this->ee->db->calls);
    }

    /**
     * Delegate a legacy view with its caller variables and archive version.
     *
     * @return void
     */
    public function testViewAdapter(): void
    {
        $this->ee->load = new LowYearlySpy(array('view' => 'legacy view'));

        self::assertSame('legacy view', $this->bridge()->view('settings', array('title' => 'Legacy'), true));
        self::assertSame(array(array('view', array('settings', array('title' => 'Legacy', 'ee_ver' => '7'), true))), $this->ee->load->calls);
    }

    /**
     * Construct the actual shipped EE2 adapter independently of current EE dispatch.
     *
     * @return EE2
     */
    private function bridge(): EE2
    {
        return new EE2(array('module' => 'low_yearly_archives', 'module_name' => 'Low Yearly Archives'));
    }
}
