<?php

final class SetupTest extends LowYearlyTestCase
{
    /**
     * Verify package metadata comes from the shipped JSON manifest.
     *
     * @return void
     */
    public function testSetupMatchesManifest(): void
    {
        $setup = require PATH_THIRD . 'low_yearly_archives/addon.setup.php';
        $manifest = json_decode(file_get_contents(PATH_THIRD . 'low_yearly_archives/addon.json'), true);

        self::assertSame($manifest['name'], $setup['name']);
        self::assertSame($manifest['version'], $setup['version']);
        self::assertSame($manifest['description'], $setup['description']);
        self::assertSame($manifest['namespace'], $setup['namespace']);
        self::assertSame(false, $setup['settings_exist']);
        self::assertSame('EEHarbor', $setup['author']);
        self::assertSame('http://eeharbor.com/low_yearly_archives/documentation', $setup['docs_url']);
    }
}
