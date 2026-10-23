<?php

final class LegacyNativeBridgeTest extends LowYearlyTestCase
{
    /**
     * Isolate version constants while preserving EE's documented session URL workaround.
     *
     * @param string $version
     * @param string $base
     * @param array $config
     * @param string|null $session
     * @return void
     * @dataProvider sessions
     */
    public function testLegacyBaseUrls($version, $base, array $config, $session): void
    {
        $data = $this->probe('legacy-url', $version, array('base' => $base, 'config' => $config));
        foreach (array('base', 'route') as $key) {
            parse_str(parse_url(html_entity_decode($data[$key]), PHP_URL_QUERY), $parameters);
            self::assertSame('addons_modules', $parameters['C']);
            self::assertSame('show_module_cp', $parameters['M']);
            self::assertSame('low_yearly_archives', $parameters['module']);
            self::assertSame('cp', $parameters['D']);
            self::assertSame($session, $parameters['S'] ?? null);
            if ($key === 'route') {
                self::assertSame('settings', $parameters['method']);
                self::assertSame('1', $parameters['extra']);
            }
            if ($key === 'base') {
                self::assertArrayNotHasKey('method', $parameters);
            }
        }
    }

    /**
     * Cover cookie, session, fingerprint, existing-directive and version-boundary cases.
     *
     * @return array
     */
    public static function sessions(): array
    {
        $base = 'https://example.test/cp.php?site=1';

        return array(
            array('2.5.5', $base, array('cp_session_type' => 's'), 'session-token'),
            array('2.5.5', $base, array('cp_session_type' => 'cs'), 'fingerprint-token'),
            array('2.5.5', $base, array('cp_session_type' => 'c'), null),
            array('2.5.5', $base . '&S=present&D=cp', array('cp_session_type' => 's'), 'present'),
            array('2.6.0', $base, array('admin_session_type' => 's', 'cp_session_type' => 'cs'), 'session-token'),
            array('2.7.3', $base, array('admin_session_type' => 'cs', 'cp_session_type' => 's'), 'fingerprint-token'),
            array('2.8.0', $base, array('admin_session_type' => 's', 'cp_session_type' => 'cs'), 'fingerprint-token'),
        );
    }

    /**
     * Translate route names, special identifiers and nullable module arguments independently.
     *
     * @param string $path
     * @param string $mode
     * @param array|null $variables
     * @param bool $module
     * @param array $expected
     * @return void
     * @dataProvider routes
     */
    public function testLegacyRoutes($path, $mode, $variables, $module, array $expected): void
    {
        $data = $this->probe('legacy-routes', '2.5.5', array(
            'base' => 'https://example.test/cp.php?site=1', 'config' => array(),
            'path' => $path, 'mode' => $mode, 'variables' => $variables, 'module' => $module,
        ));
        parse_str(parse_url(html_entity_decode($data['url']), PHP_URL_QUERY), $actual);
        $expected += array('site' => '1', 'D' => 'cp');
        ksort($expected);
        ksort($actual);
        self::assertSame($expected, $actual);
        self::assertSame('set_right_nav', $data['nav'][0][0]);
        self::assertSame('https://example.test/docs', $data['nav'][0][1][0]['Docs']);
        parse_str(parse_url(html_entity_decode($data['nav'][0][1][0]['Settings']), PHP_URL_QUERY), $navigation);
        self::assertSame('low_yearly_archives', $navigation['module']);
        self::assertSame('settings', $navigation['method']);
    }

    /**
     * Supply mapped, unmapped, optional and query-encoding route contracts.
     *
     * @return array
     */
    public static function routes(): array
    {
        return array(
            array('listing', 'edit', array('filter_by_channel' => 4), false, array('C' => 'content_edit', 'channel_id' => '4')),
            array('listing', '', array(), false, array('C' => 'content_edit')),
            array('publish', 'create', array('channel_id' => 4), false, array('C' => 'content_publish', 'M' => 'entry_form', 'channel_id' => '4')),
            array('publish', 'edit', array('entry_id' => 9), false, array('C' => 'content_publish', 'M' => 'entry_form', 'entry_id' => '9')),
            array('publish', 'preview', array(), false, array('C' => 'content_publish', 'M' => 'preview')),
            array('publish', '', array(), false, array('C' => 'content_publish')),
            array('members', 'groups', array(), false, array('C' => 'members', 'M' => 'member_group_manager')),
            array('members', 'profile', array(), false, array('C' => 'members', 'M' => 'profile')),
            array('channels', 'create', array(), false, array('C' => 'admin_content', 'M' => 'channel_add')),
            array('channels', 'edit', array(), false, array('C' => 'admin_content', 'M' => 'edit')),
            array('addons', '', array(), false, array('C' => 'addons_modules')),
            array('tools', 'utility', array('limit' => 3), false, array('C' => 'tools', 'M' => 'utility', 'limit' => '3')),
            array('', 'settings', array('limit' => 3), true, array('C' => 'addons_modules', 'M' => 'show_module_cp', 'module' => 'low_yearly_archives', 'method' => 'settings', 'limit' => '3')),
            array('', 'index', null, true, array('C' => 'addons_modules', 'M' => 'show_module_cp', 'module' => 'low_yearly_archives', 'method' => 'index')),
            array('tools', '', array('search' => 'two&extra=1'), false, array('C' => 'tools', 'search' => 'two&extra=1')),
        );
    }

    /**
     * Keep the legacy function/library switch at its actual EE 2.6 boundary.
     *
     * @param string $version
     * @param bool $legacy
     * @return void
     * @dataProvider formattingVersions
     */
    public function testNativeFormattingBoundary($version, $legacy): void
    {
        $data = $this->probe('legacy-formatting', $version, array());
        self::assertSame('slug:example title', $data['slug']);
        self::assertSame($legacy ? 'legacy slashes' : 'slashes:https://example.test//path', $data['slashes']);
        self::assertSame($legacy ? array(array('remove_double_slashes', array('https://example.test//path'))) : array(), $data['functions']);
        self::assertSame($legacy ? array() : array(array('helper', array('string'))), $data['loader']);
        $helpers = array(array('url_title', 'example title'));
        if (!$legacy) {
            $helpers[] = array('reduce_double_slashes', 'https://example.test//path');
        }
        self::assertSame($helpers, $data['helpers']);
    }

    /**
     * Include EE releases on each side of the helper migration.
     *
     * @return array
     */
    public static function formattingVersions(): array
    {
        return array(array('2.5.5', true), array('2.6.0', false));
    }

    /**
     * Write dynamic templates only into an owned fixture while retaining the fallback view.
     *
     * @param bool $writable
     * @return void
     * @dataProvider viewPermissions
     */
    public function testNativeDynamicViews($writable): void
    {
        $data = $this->probe('legacy-view', '2.11.9', array('writable' => $writable));
        self::assertSame('rendered view', $data['result']);
        self::assertSame(array(array('view', array($writable ? 'dynamic' : 'original', array('message' => 'value', 'ee_ver' => '2'), true))), $data['calls']);
        self::assertSame($writable ? '<p>dynamic</p>' : null, $data['contents']);
        $source = 'phar://' . PATH_THIRD . 'low_yearly_archives/low_yearly_archives.phar/FluxCapacitor/Bridge/EE2.php';
        self::assertSame(hash_file('sha256', $source), $data['source_sha256']);
        self::assertSame($source, $data['loaded_from']);
        self::assertDirectoryDoesNotExist($data['directory']);
    }

    /**
     * Cover writable and permission-denied view directories in separate native children.
     *
     * @return array
     */
    public static function viewPermissions(): array
    {
        return array('writable' => array(true), 'read only' => array(false));
    }

    /**
     * Run native shipped-source execution and reject diagnostics before decoding its output.
     *
     * @param string $scenario
     * @param string $version
     * @param array $input
     * @return array
     */
    private function probe($scenario, $version, array $input): array
    {
        list($status, $stdout, $stderr) = LowYearlyNativeProcess::run(array(__DIR__ . '/../../support/native.php', $scenario, $version, json_encode($input, JSON_THROW_ON_ERROR)));
        self::assertSame(0, $status, $stdout . $stderr);
        self::assertSame('', $stderr);
        $data = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
