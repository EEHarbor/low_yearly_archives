<?php

use Low\YearlyArchives\FluxCapacitor\Conduit\GlobalFieldSettings;

final class GlobalFieldSettingsTest extends LowYearlyTestCase
{
    /**
     * Return null for an uninstalled fieldtype and avoid creating a settings row.
     *
     * @return void
     */
    public function testAbsentFieldtype(): void
    {
        $settings = new GlobalFieldSettings();

        self::assertSame('low_yearly_archives', $settings->fieldtype_name);
        self::assertNull($settings->getSettings());
        self::assertNull($settings->getSetting('missing'));
        self::assertFalse($settings->updateSetting('limit', 2));
        self::assertSame(array('get', array('fieldtypes')), end($this->ee->db->calls));
    }

    /**
     * Round-trip serialized values including null, zero, false and nested arrays.
     *
     * @return void
     */
    public function testReadsAndUpdatesFieldtypeSettings(): void
    {
        $values = array('zero' => 0, 'false' => false, 'null' => null, 'nested' => array('a' => 'b'));
        $this->ee->db->rows = array(array('settings' => base64_encode(serialize($values))));
        $settings = new GlobalFieldSettings('custom_field');

        self::assertSame('custom_field', $settings->fieldtype_name);
        self::assertSame($values, $settings->getSettings());
        self::assertSame(0, $settings->getSetting('zero'));
        self::assertFalse($settings->getSetting('false'));
        self::assertNull($settings->getSetting('null'));
        self::assertTrue($settings->updateSetting('added', 'value'));
        self::assertSame(array('update', array('fieldtypes', array('settings' => base64_encode(serialize($values + array('added' => 'value')))))), end($this->ee->db->calls));
    }

    /**
     * Preserve an existing empty serialized settings row as an empty array.
     *
     * @return void
     */
    public function testEmptySerializedSettings(): void
    {
        $this->ee->db->rows = array(array('settings' => base64_encode(serialize(false))));
        $settings = new GlobalFieldSettings();

        self::assertSame(array(), $settings->getSettings());
        self::assertNull($settings->getSetting('missing'));
        self::assertTrue($settings->updateSetting('first', 0));
    }

    /**
     * Keep missing-key diagnostics visible independently of successful updates.
     *
     * @return void
     */
    public function testMissingKeyDoesNotEmitSourceDiagnostics(): void
    {
        $this->ee->db->rows = array(array('settings' => base64_encode(serialize(array('other' => 'value')))));

        self::assertNull((new GlobalFieldSettings())->getSetting('missing'));
    }

    /**
     * Normalize empty stored settings and reject malformed bytes without native diagnostics.
     *
     * @param string|null $stored
     * @return void
     * @dataProvider storedSettingsBoundaries
     */
    public function testStoredSettingsBoundaries($stored): void
    {
        $this->ee->db->rows = array(array('settings' => $stored));

        self::assertSame(array(), (new GlobalFieldSettings())->getSettings());
        self::assertSame(array(
            array('select', array('settings')),
            array('where', array('name', 'low_yearly_archives')),
            array('get', array('fieldtypes')),
        ), $this->ee->db->calls);
    }

    /**
     * Include legacy null storage, empty/non-base64 bytes and valid empty serialized values.
     *
     * @return array
     */
    public static function storedSettingsBoundaries(): array
    {
        return array(
            'null storage' => array(null), 'empty bytes' => array(''),
            'invalid base64 bytes' => array('%%%'),
            'invalid serialized bytes' => array(base64_encode('not serialized')),
            'serialized zero' => array(base64_encode(serialize(0))),
            'serialized empty array' => array(base64_encode(serialize(array()))),
        );
    }
}
