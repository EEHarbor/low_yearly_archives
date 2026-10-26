<?php

final class McpLicenseActionTest extends LowYearlyTestCase
{
    /**
     * Preserve license validation, site isolation and exact persistence through native redirects.
     *
     * @param array $input
     * @param array $writes
     * @param string $title
     * @param string $severity
     * @return void
     * @dataProvider licenseActions
     */
    public function testLicenseAction(array $input, array $writes, $title, $severity): void
    {
        list($status, $stdout, $stderr) = LowYearlyNativeProcess::run(array(__DIR__ . '/../../support/native.php', 'save-license', '7.5.17', json_encode($input, JSON_THROW_ON_ERROR)));
        self::assertSame(0, $status, $stdout . $stderr);
        self::assertSame('', $stderr);
        $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('addons/settings/low_yearly_archives/license', $result['redirect']);
        $actualWrites = array_values(array_filter($result['database'], function ($call) { return in_array($call[0], array('insert', 'update', 'delete'), true); }));
        self::assertSame($writes, $actualWrites);
        $lookups = array_values(array_filter($result['database'], function ($call) { return $call[0] === 'where' && $call[1][0] === 'license_key'; }));
        self::assertSame($input['lookup'] === null ? array() : array(array('where', array('license_key', $input['lookup']))), $lookups);
        self::assertContains(array('withTitle', array($title)), $result['alerts']);
        self::assertContains(array($severity, array()), $result['alerts']);
        self::assertCount(1, array_filter($result['alerts'], function ($call) { return $call[0] === 'defer'; }));
        foreach ($result['database'] as $index => $call) {
            if (!in_array($call[0], array('update', 'delete'), true)) {
                continue;
            }
            self::assertSame(array(
                array('where', array('site_id', $input['site_id'])),
                array('where', array('addon', 'Low\\YearlyArchives')),
            ), array_slice($result['database'], $index - 2, 2));
        }
    }

    /**
     * Supply sanitized, boundary, ignored-site, duplicate and numeric-ID storage shapes.
     *
     * @return array
     */
    public static function licenseActions(): array
    {
        $base = array('site_id' => 1, 'post' => array('license_key' => 'key-1', 'ignore_site' => '0'), 'licenses' => array(), 'sites' => array(), 'count' => 0, 'lookup' => 'key-1');
        $data = array('site_id' => 1, 'addon' => 'Low\\YearlyArchives', 'license_key' => 'key-1', 'ignore_site' => 0);
        $success = 'License Key Updated';
        $same = array('site_id' => 1, 'addon' => 'Low\\YearlyArchives', 'license_key' => 'key-1');
        $cases = array();
        $cases['new key'] = array($base, array(array('insert', array('eeharbor_licenses', $data))), $success, 'asSuccess');
        $input = $base;
        $input['post']['license_key'] = ' ab_C-12<> ';
        $input['lookup'] = 'abC-12';
        $insert = $data;
        $insert['license_key'] = 'abC-12';
        $cases['sanitized key'] = array($input, array(array('insert', array('eeharbor_licenses', $insert))), $success, 'asSuccess');
        $input['post']['license_key'] = str_repeat('A', 50);
        $input['lookup'] = str_repeat('A', 50);
        $insert['license_key'] = str_repeat('A', 50);
        $cases['maximum length'] = array($input, array(array('insert', array('eeharbor_licenses', $insert))), $success, 'asSuccess');
        $input['post']['license_key'] = str_repeat('A', 51);
        $input['lookup'] = null;
        $cases['over maximum length'] = array($input, array(), 'License Key Too Long', 'asIssue');
        $input = $base;
        $input['post'] = array('license_key' => '', 'ignore_site' => '1');
        $input['lookup'] = 'low-yearly-archives-ignore-site-1';
        $insert = $data;
        $insert['license_key'] = 'low-yearly-archives-ignore-site-1';
        $insert['ignore_site'] = 1;
        $cases['ignore site'] = array($input, array(array('insert', array('eeharbor_licenses', $insert))), $success, 'asSuccess');
        $input['licenses'] = array($same);
        $input['check_key_rows'] = array();
        $input['count'] = 1;
        $cases['ignore existing site'] = array($input, array(array('update', array('eeharbor_licenses', $insert))), $success, 'asSuccess');
        $input = $base;
        $input['licenses'] = array($same);
        $input['count'] = 1;
        $cases['update current key'] = array($input, array(array('update', array('eeharbor_licenses', $data))), $success, 'asSuccess');
        $replacement = $input;
        $replacement['post']['license_key'] = 'key-2';
        $replacement['lookup'] = 'key-2';
        $replacement['check_key_rows'] = array();
        $replacementData = $data;
        $replacementData['license_key'] = 'key-2';
        $cases['replace current key'] = array($replacement, array(array('update', array('eeharbor_licenses', $replacementData))), $success, 'asSuccess');
        $input['licenses'][0]['site_id'] = '1';
        $cases['numeric string database id'] = array($input, array(array('update', array('eeharbor_licenses', $data))), $success, 'asSuccess');
        $input['site_id'] = '1';
        $insert = $data;
        $insert['site_id'] = '1';
        $cases['matching string ids'] = array($input, array(array('update', array('eeharbor_licenses', $insert))), $success, 'asSuccess');
        $input = $base;
        $input['licenses'] = array(array('site_id' => 2, 'addon' => 'Low\\YearlyArchives', 'license_key' => 'key-1'));
        $prefix = 'That License Key is already in use on one of your other MSM sites:<br /><br />';
        $cases['foreign missing site'] = array($input, array(), $prefix . 'Error: Could not locate MSM Site name.<p>If this MSM site is no longer installed, please remove your license key from the eeharbor_licenses database table.</p>', 'asIssue');
        $input['sites'] = array(array('site_label' => 'Other Site'));
        $cases['foreign named site'] = array($input, array(), $prefix . str_repeat('&nbsp;', 10) . 'Other Site', 'asIssue');
        $input = $base;
        $input['licenses'] = array(array('site_id' => 1, 'addon' => 'EEHarbor\\OtherAddon', 'license_key' => 'key-1'));
        $cases['foreign add-on'] = array($input, array(), 'This license key is already being used for another installed add-on:  Other Addon', 'asIssue');
        $input = $base;
        $input['post']['license_key'] = '';
        $input['lookup'] = '';
        $input['licenses'] = array(array('site_id' => 1, 'addon' => 'Low\\YearlyArchives', 'license_key' => 'low-yearly-archives-ignore-site-1'));
        $input['check_key_rows'] = array();
        $input['count'] = 1;
        $cases['stop ignoring site'] = array($input, array(array('delete', array('eeharbor_licenses'))), $success, 'asSuccess');
        $input['licenses'] = array($same);
        $insert = $data;
        $insert['license_key'] = '';
        $cases['clear ordinary key'] = array($input, array(array('update', array('eeharbor_licenses', $insert))), $success, 'asSuccess');

        return $cases;
    }

    /**
     * Construct the real CP controller when this package has no optional sidebar class.
     *
     * @return void
     */
    public function testNativeControlPanelConstructorWithoutNav(): void
    {
        $input = array('site_id' => 1, 'post' => array(), 'licenses' => array(), 'sites' => array(), 'count' => 0);
        list($status, $stdout, $stderr) = LowYearlyNativeProcess::run(array(__DIR__ . '/../../support/native.php', 'mcp-constructor', '7.5.17', json_encode($input, JSON_THROW_ON_ERROR)));
        self::assertSame(0, $status, $stdout . $stderr);
        self::assertSame('', $stderr);
        self::assertSame(array('version' => '4.1.1', 'nav' => null), json_decode($stdout, true, 512, JSON_THROW_ON_ERROR));
    }
}
