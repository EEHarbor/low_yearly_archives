<?php

use Low\YearlyArchives\FluxCapacitor\Conduit\StaticCache;
use Low\YearlyArchives\FluxCapacitor\Conduit\Version;

final class VersionTest extends LowYearlyTestCase
{
    /**
     * Prepare the license table verification collaborators.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->ee->db->tableExists = true;
        $this->ee->db->queryRows = array(array('Column_name' => 'license_key', 'Non_unique' => '0'));
        $this->ee->load = new LowYearlySpy(array('dbforge' => null));
        $this->ee->dbforge = new LowYearlySpy(array('add_field' => null, 'add_key' => null, 'create_table' => null, 'add_column' => null));
    }

    /**
     * Verify the healthy primary key and cached license-table result.
     *
     * @return void
     */
    public function testHealthyLicenseTableAndCachedResult(): void
    {
        $this->ee->db->data_cache['table_names'] = array('stale');
        $version = new Version();
        self::assertFalse($version->verifyLicenseTable());
        self::assertArrayNotHasKey('table_names', $this->ee->db->data_cache);
        self::assertSame(array(), $this->ee->dbforge->calls);
        self::assertSame(array(), $this->ee->load->calls);
        self::assertCount(3, $this->ee->db->calls);
    }

    /**
     * Create absent schema components and retain corrupt-key state.
     *
     * @param array $primaryKeys
     * @return void
     * @dataProvider corruptKeys
     */
    public function testMissingLicenseSchema(array $primaryKeys): void
    {
        $this->ee->db->tableExists = false;
        $this->ee->db->fieldExists = false;
        $this->ee->db->queryRows = $primaryKeys;
        $version = new Version();

        self::assertTrue($version->verifyLicenseTable());
        self::assertContains(array('create_table', array('eeharbor_licenses')), $this->ee->dbforge->calls);
        self::assertContains(array('add_key', array('license_key', true)), $this->ee->dbforge->calls);
        self::assertContains(array('add_key', array(array('site_id', 'addon'))), $this->ee->dbforge->calls);
        self::assertContains(array('add_column', array('eeharbor_licenses', array('ignore_site' => array('type' => 'int(1)', 'unsigned' => true, 'null' => false, 'default' => '0')))), $this->ee->dbforge->calls);
        self::assertSame(array('site_id', 'addon', 'license_key', 'ignore_site'), array_keys($this->ee->dbforge->calls[0][1][0]));
    }

    /**
     * Supply missing, wrong-column and non-unique primary key shapes.
     *
     * @return array
     */
    public static function corruptKeys(): array
    {
        return array(array(array()), array(array(array('Column_name' => 'id', 'Non_unique' => '0'))), array(array(array('Column_name' => 'license_key', 'Non_unique' => '1'))));
    }

    /**
     * Insert/update only the selected add-on/site and support cached existence.
     *
     * @return void
     */
    public function testLicensePersistence(): void
    {
        $version = new Version();
        self::assertFalse($version->keyExistsForAddon());
        $this->ee->db->count = 1;
        self::assertFalse($version->keyExistsForAddon());
        StaticCache::delete('keyExistsForAddon');
        self::assertTrue($version->keyExistsForAddon());
        $version->saveLicenseKey(array('license_key' => 'key-1'));
        self::assertContains(array('update', array('eeharbor_licenses', array('license_key' => 'key-1', 'site_id' => 1, 'addon' => 'Low\\YearlyArchives'))), $this->ee->db->calls);
        StaticCache::delete('keyExistsForAddon');
        $this->ee->db->count = 0;
        $version->saveLicenseKey(array('license_key' => 'key-2', 'site_id' => 3, 'addon' => 'Other\\Addon'), 'Other\\Addon');
        self::assertSame(array('insert', array('eeharbor_licenses', array('license_key' => 'key-2', 'site_id' => 3, 'addon' => 'Other\\Addon'))), end($this->ee->db->calls));
        $version->deleteLicenseKey();
        self::assertSame(array('delete', array('eeharbor_licenses')), end($this->ee->db->calls));
    }

    /**
     * Sanitize stored license keys and preserve ignored-site flags.
     *
     * @return void
     */
    public function testReadsLicenseAndIgnoreSite(): void
    {
        $version = new Version();
        self::assertSame('', $version->getLicenseKey());
        self::assertFalse($version->getIgnoreSite());
        $this->ee->db->rows = array(array('license_key' => 'ab_C-12<>', 'ignore_site' => '1'));
        self::assertSame('abC-12', $version->getLicenseKey());
        self::assertSame('1', $version->getIgnoreSite());
    }

    /**
     * Generate encrypted update pixels without making network requests.
     *
     * @return void
     */
    public function testUpdatePixelIsEncryptedAndNotDuplicated(): void
    {
        $version = new Version();
        $footer = array();
        $this->ee->cp = $this->getMockBuilder(stdClass::class)->addMethods(array('get_foot', 'add_to_foot'))->getMock();
        $this->ee->cp->method('get_foot')->willReturnCallback(function () use (&$footer) { return $footer; });
        $this->ee->cp->method('add_to_foot')->willReturnCallback(function ($html) use (&$footer) { $footer[] = $html; });
        $version->check();

        self::assertCount(2, $footer);
        self::assertStringContainsString('<div id="low_yearly_archives__up"', $footer[0]);
        self::assertStringContainsString('background:url(&#104;&#116;&#116;&#112;&#115;', $footer[0]);
        self::assertSame(1, preg_match('/\?([a-f0-9]{1024})/', $footer[0]));
        self::assertStringContainsString('<script type="text/javascript">', $footer[1]);
        self::assertTrue($version->check());
        self::assertCount(2, $footer);
        $footer = array('unrelated footer');
        $oldGet = $_GET;
        try {
            $_GET['ldev'] = 1;
            $this->ee->config->values['site_license_key'] = 'site-license';
            $this->ee->db->tableRows['modules'] = array(array('module_version' => '2.1.0'));
            $version->check();
            self::assertStringContainsString('background:url(&#104;&#116;&#116;&#112;&#58;', $footer[1]);
        } finally {
            $_GET = $oldGet;
        }
    }

    /**
     * Encode every byte of an update URL and preserve an empty input.
     *
     * @return void
     */
    public function testSpiderSafeEncoding(): void
    {
        $version = new Version();
        self::assertSame('', $this->invoke($version, 'spiderSafe', array('')));
        self::assertSame('&#97;&#47;&#38;', $this->invoke($version, 'spiderSafe', array('a/&')));
        self::assertNotFalse($this->invoke($version, 'getOpenSSLPublicKey'));
    }
}
