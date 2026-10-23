<?php

final class LowYearlyMutableFacade extends stdClass
{
    /**
     * Install an EE service using the container's legacy set contract.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     * @throws RuntimeException
     */
    public function set($name, $value)
    {
        if (property_exists($this, $name)) {
            throw new RuntimeException('Cannot overwrite ' . $name . ' on the loader.');
        }
        $this->$name = $value;
    }

    /**
     * Remove an EE service using the container's legacy remove contract.
     *
     * @param string $name
     * @return void
     */
    public function remove($name)
    {
        unset($this->$name);
    }
}

final class LowYearlyNavHeader
{
    public $title;
    public $url;
    public $button;
    public $active = false;

    /**
     * Store the sidebar header's label.
     *
     * @param string $title
     * @return void
     */
    public function __construct($title)
    {
        $this->title = $title;
    }

    /**
     * Assign a URL and preserve fluent header chaining.
     *
     * @param string|LowYearlyUrl $url
     * @return self
     */
    public function withUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Add a header action with the sidebar button contract.
     *
     * @param string $text
     * @param LowYearlyUrl $url
     * @return self
     */
    public function withButton($text, $url)
    {
        $this->button = array('text' => $text, 'url' => $url);

        return $this;
    }

    /**
     * Mark the matching header as active.
     *
     * @return self
     */
    public function isActive()
    {
        $this->active = true;

        return $this;
    }
}

final class LowYearlySidebar
{
    public $headers = array();

    /**
     * Return the sidebar builder.
     *
     * @return self
     */
    public function make()
    {
        return $this;
    }

    /**
     * Add and return a fluent header object.
     *
     * @param string $title
     * @return LowYearlyNavHeader
     */
    public function addHeader($title)
    {
        $header = new LowYearlyNavHeader($title);
        $this->headers[] = $header;

        return $header;
    }
}

final class ModernBridgeAdaptersTest extends LowYearlyTestCase
{
    /**
     * Install a mutable facade and isolate helper-call state.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $facade = new LowYearlyMutableFacade();
        foreach (get_object_vars($this->ee) as $name => $value) {
            $facade->$name = $value;
        }
        $this->ee = $facade;
        $GLOBALS['low_yearly_ee'] = $facade;
        $GLOBALS['low_yearly_helper_calls'] = array();
    }

    /**
     * Remove this fixture's helper state before restoring the EE request.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($GLOBALS['low_yearly_helper_calls']);
        parent::tearDown();
    }

    /**
     * Forward collaborator calls and retain each version's EE service boundary.
     *
     * @param string $class
     * @return void
     * @dataProvider bridges
     */
    public function testLegacyAndModernDelegations($class): void
    {
        $bridge = $this->bridge($class);
        $this->ee->legacy_api = new LowYearlySpy(array('instantiate' => null));
        $this->ee->cp = new LowYearlySpy(array('get_foot' => array('existing'), 'add_to_foot' => null));
        $this->ee->load = new LowYearlySpy(array('helper' => null));
        $this->ee->addons_model = new LowYearlySpy(array('module_installed' => true));
        self::assertNull($bridge->instantiate('channel_entries'));
        self::assertSame(array(array('instantiate', array('channel_entries'))), $this->ee->legacy_api->calls);
        self::assertSame(array('existing'), $bridge->getFooter());
        $bridge->javascript_to_page('window.ready = true;');
        self::assertSame(array('add_to_foot', array('<script type="text/javascript">window.ready = true;</script>')), end($this->ee->cp->calls));
        self::assertTrue($bridge->module_installed('Channel'));
        self::assertSame(array(array('module_installed', array('Channel'))), $this->ee->addons_model->calls);
        $bridge->overwriteEEClass('custom_service', array('value'));
        self::assertSame(array('value'), $this->ee->custom_service);
        $bridge->removeEEClass('custom_service');
        self::assertFalse(property_exists($this->ee, 'custom_service'));
        self::assertSame('slashes:https://example.test//path', $bridge->reduce_double_slashes('https://example.test//path'));
        self::assertSame(array(array('helper', array('string'))), $this->ee->load->calls);
        self::assertSame(array(array('reduce_double_slashes', 'https://example.test//path')), $GLOBALS['low_yearly_helper_calls']);
    }

    /**
     * Preserve already-loaded services, including null, when replacement is rejected.
     *
     * @param string $class
     * @param mixed $existing
     * @return void
     * @dataProvider existingServices
     */
    public function testAlreadyLoadedServicesCannotBeOverwritten($class, $existing): void
    {
        $this->ee->existing_service = $existing;
        try {
            $this->bridge($class)->overwriteEEClass('existing_service', 'replacement');
            self::fail('The EE facade must reject replacement of a loaded service.');
        } catch (RuntimeException $exception) {
            self::assertSame('Cannot overwrite existing_service on the loader.', $exception->getMessage());
        }
        self::assertTrue(property_exists($this->ee, 'existing_service'));
        self::assertSame($existing, $this->ee->existing_service);
    }

    /**
     * Route text formatting, XSS and variable parsing through the supported APIs.
     *
     * @param string $class
     * @return void
     * @dataProvider bridges
     */
    public function testTextSecurityAndVariableAdapters($class): void
    {
        $bridge = $this->bridge($class);
        $input = array('field' => '<script>text</script>');
        $xss = new LowYearlySpy(array('clean' => array('field' => 'cleaned')));
        $this->ee->services['Security/XSS'] = $xss;
        self::assertSame(array('field' => 'cleaned'), $bridge->xss_clean($input));
        self::assertSame(array(array('clean', array($input))), $xss->calls);
        $parsed = array('single' => array('title'));
        $this->ee->functions = new LowYearlySpy(array('assign_variables' => $parsed, 'assign_parameters' => array('limit' => '4')));
        $parser = new LowYearlySpy(array('extractVariables' => $parsed, 'parseTagParameters' => array('limit' => '4')));
        $this->ee->services['Variables/Parser'] = $parser;
        self::assertSame($parsed, $bridge->assign_variables('{title}'));
        self::assertSame(array('limit' => '4'), $bridge->assign_parameters('limit="4"', array('limit' => '2')));
        if (substr($class, -1) === '3') {
            self::assertSame('slug:example title', $bridge->url_title('  EXAMPLE Title  '));
            self::assertSame('url:example.test', $bridge->prep_url('example.test'));
            self::assertSame(array(array('url_title', 'example title'), array('prep_url', 'example.test')), $GLOBALS['low_yearly_helper_calls']);
            self::assertCount(2, $this->ee->functions->calls);

            return;
        }
        $format = $this->getMockBuilder(stdClass::class)->addMethods(array('make', 'urlSlug', 'compile', 'url'))->getMock();
        $formatCalls = array();
        $format->expects(self::exactly(2))->method('make')->willReturnCallback(function ($type, $text) use (&$formatCalls, $format) {
            $formatCalls[] = array($type, $text);

            return $format;
        });
        $format->expects(self::once())->method('urlSlug')->willReturnSelf();
        $format->expects(self::once())->method('compile')->willReturn('example-title');
        $format->expects(self::once())->method('url')->willReturn('https://example.test');
        $this->ee->services['Format'] = $format;
        self::assertSame('example-title', $bridge->url_title('  EXAMPLE Title  '));
        self::assertSame('https://example.test', $bridge->prep_url('example.test'));
        self::assertSame(array(array('Text', 'example title'), array('Text', 'example.test')), $formatCalls);
        self::assertSame(array(
            array('extractVariables', array('{title}')),
            array('parseTagParameters', array('limit="4"', array('limit' => '2'))),
        ), $parser->calls);
    }

    /**
     * Render both stored and dynamic views with caller/default view variables.
     *
     * @param string $class
     * @return void
     * @dataProvider bridges
     */
    public function testViewAdapters($class): void
    {
        $bridge = $this->bridge($class);
        $this->ee->view = (object) array('cp_page_title' => 'Page heading');
        $view = $this->getMockBuilder(stdClass::class)->addMethods(array('make', 'makeFromString', 'render'))->getMock();
        $view->expects(self::once())->method('make')->with('low_yearly_archives:settings')->willReturnSelf();
        $view->expects(self::once())->method('makeFromString')->with('<p>dynamic</p>')->willReturnSelf();
        $variables = array();
        $view->method('render')->willReturnCallback(function ($data) use (&$variables) { $variables[] = $data; return 'rendered body'; });
        $this->ee->services['View'] = $view;
        $result = $bridge->view('settings', array('custom' => 0));
        self::assertSame(array('heading' => 'Page heading', 'breadcrumb' => array('/admin/addons/settings/low_yearly_archives/' => 'Low Yearly Archives'), 'body' => 'rendered body'), $result);
        self::assertSame('7', $variables[0]['ee_ver']);
        self::assertSame(0, $variables[0]['custom']);
        self::assertSame('Page heading', $variables[0]['cp_page_title']);
        self::assertSame('addons/settings/low_yearly_archives', $variables[0]['base_url']->path);
        $bridge->view('ignored', array('base_url' => '/custom', 'cp_page_title' => 'Caller heading'), true, '<p>dynamic</p>');
        self::assertSame('/custom', $variables[1]['base_url']);
        self::assertSame('Caller heading', $variables[1]['cp_page_title']);
    }

    /**
     * Build internal/external sidebar links and mark the matching index header.
     *
     * @param string $class
     * @return void
     * @dataProvider bridges
     */
    public function testSidebarAdapters($class): void
    {
        $bridge = $this->bridge($class);
        $sidebar = new LowYearlySidebar();
        $this->ee->services['CP/Sidebar'] = $sidebar;
        $this->ee->view = (object) array('cp_page_title' => 'original');
        $this->ee->cp = new LowYearlySpy(array('set_breadcrumb' => null));
        $bridge->getNav();
        self::assertSame('original', $this->ee->view->cp_page_title);
        $this->ee->uri->path = 'cp/addons/settings/low_yearly_archives';
        $bridge->getNav(array('/' => 'Overview', 'entries' => 'Entries', 'https://example.test/docs' => 'Docs'), array('entries' => array('create' => 'New', 'edit' => 'Edit')));
        self::assertCount(3, $sidebar->headers);
        self::assertTrue($sidebar->headers[0]->active);
        self::assertFalse($sidebar->headers[1]->active);
        self::assertSame('https://example.test/docs', $sidebar->headers[2]->url);
        self::assertSame('addons/settings/low_yearly_archives/index', $sidebar->headers[0]->url->path);
        self::assertSame(array('text', 'url'), array_keys($sidebar->headers[1]->button));
        self::assertSame('Edit', $sidebar->headers[1]->button['text']);
        self::assertSame('addons/settings/low_yearly_archives/edit', $sidebar->headers[1]->button['url']->path);
        self::assertSame('Overview', $this->ee->view->cp_page_title);
        self::assertCount(1, $this->ee->cp->calls);
    }

    /**
     * Keep a mapped sidebar label a string rather than replacing it with a header object.
     *
     * @param string $class
     * @return void
     * @dataProvider bridges
     */
    public function testMappedSidebarTitleRemainsText($class): void
    {
        $this->ee->services['CP/Sidebar'] = new LowYearlySidebar();
        $this->ee->view = (object) array('cp_page_title' => 'original');
        $this->ee->cp = new LowYearlySpy(array('set_breadcrumb' => null));
        $this->ee->uri->path = 'cp/addons/settings/low_yearly_archives/details';
        $this->bridge($class)->getNav(array('entries' => 'Entries'), array(), array('details' => 'entries'));

        self::assertSame('Entries', $this->ee->view->cp_page_title);
    }

    /**
     * Supply modern bridge implementations and the inherited EE6 adapter.
     *
     * @return array
     */
    public static function bridges(): array
    {
        return array_map(function ($version) { return array('Low\\YearlyArchives\\FluxCapacitor\\Bridge\\EE' . $version); }, array(3, 4, 5, 6));
    }

    /**
     * Supply object and null service registrations for each supported bridge.
     *
     * @return array
     */
    public static function existingServices(): array
    {
        $cases = array();
        foreach (self::bridges() as $bridge) {
            $cases[] = array($bridge[0], (object) array('existing' => true));
            $cases[] = array($bridge[0], null);
        }

        return $cases;
    }

    /**
     * Construct the bridge with the shipped package's metadata.
     *
     * @param string $class
     * @return object
     */
    private function bridge($class)
    {
        return new $class(array('module' => 'low_yearly_archives', 'module_name' => 'Low Yearly Archives'));
    }
}
