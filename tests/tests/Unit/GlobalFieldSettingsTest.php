<?php

use Low\YearlyArchives\FluxCapacitor\Conduit\GlobalFieldSettings;

final class GlobalFieldSettingsTest extends LowYearlyTestCase
{
    /**
     * Preserve direct ArrayAccess reads even when offsetExists returns false.
     *
     * @return void
     */
    public function testGlobalSettingsRetainNativeArrayAccessValues(): void
    {
        $wire = base64_encode(serialize(new StoredSettingsArrayAccess()));
        $this->ee->db->rows = array(array('settings' => $wire));
        self::assertInstanceOf(StoredSettingsArrayAccess::class, (new GlobalFieldSettings())->getSettings());
        StoredSettingsArrayAccess::$calls = array();
        $this->ee->db->rows = array(array('settings' => $wire));
        self::assertSame('computed:missing', (new GlobalFieldSettings())->getSetting('missing'));
        self::assertSame(array('get:missing'), StoredSettingsArrayAccess::$calls);
    }

    /**
     * Preserve native string offsets instead of treating all non-arrays as absent keys.
     *
     * @return void
     */
    public function testGlobalSettingsRetainNativeStringOffsets(): void
    {
        foreach (array('0' => 's', '-1' => 'r') as $name => $expected) {
            $this->ee->db->rows = array(array('settings' => base64_encode(serialize('stored scalar'))));
            self::assertSame($expected, (new GlobalFieldSettings())->getSetting($name));
        }
    }

    /**
     * Keep unusable offsets on the quiet missing-setting fallback without changing stored values.
     *
     * @return void
     */
    public function testInvalidOffsetContainersRetainMissingSettingFallback(): void
    {
        foreach (array('stored scalar', 42, 1.5, true, (object) array('name' => 'A')) as $value) {
            $wire = base64_encode(serialize($value));
            $this->ee->db->rows = array(array('settings' => $wire));
            self::assertSame(serialize($value), serialize((new GlobalFieldSettings())->getSettings()));
            $this->ee->db->rows = array(array('settings' => $wire));
            self::assertNull((new GlobalFieldSettings())->getSetting('missing'));
        }
    }

    /**
     * Preserve truthy native stored values and non-strict base64 carrier decoding.
     *
     * @return void
     */
    public function testGlobalSettingsRetainTruthyNativeValues(): void
    {
        foreach (array((object) array('name' => 'A'), 'stored scalar', 42) as $value) {
            $wire = '!' . base64_encode(serialize($value));
            $this->ee->db->rows = array(array('settings' => $wire));
            self::assertSame(serialize($value), serialize((new GlobalFieldSettings())->getSettings()));
        }
    }

    /**
     * Report failed settings persistence without replacing its serialized representation.
     *
     * @return void
     */
    public function testFailedSettingsWriteReturnsFalse(): void
    {
        $this->ee->db->rows = array(array('settings' => base64_encode(serialize(array('old' => 'value')))));
        $this->ee->db->writeResult = false;
        self::assertFalse((new GlobalFieldSettings())->updateSetting('new', 0));
        self::assertSame(array('update', array('fieldtypes', array('settings' => base64_encode(serialize(array('old' => 'value', 'new' => 0)))))), end($this->ee->db->calls));
    }

    /**
     * Preserve historical escaped string settings through the actual archive reader.
     *
     * @return void
     */
    public function testLegacyEscapedSettingsRemainReadable(): void
    {
        $this->ee->db->rows = array(array('settings' => base64_encode('a:1:{s:4:"name";S:1:"\\41";}')));
        self::assertSame(array('name' => 'A'), (new GlobalFieldSettings())->getSettings());
    }

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

class StoredSettingsArrayAccess implements \ArrayAccess
{
    public static $calls = array();

    /**
     * Model a computed offset without material storage.
     *
     * @param mixed $name Offset name.
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($name) { self::$calls[] = 'exists:' . $name; return false; }

    /**
     * Return the native computed setting value.
     *
     * @param mixed $name Offset name.
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($name) { self::$calls[] = 'get:' . $name; return 'computed:' . $name; }

    /**
     * Observe a native offset write.
     *
     * @param mixed $name Offset name.
     * @param mixed $value Setting value.
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($name, $value) { self::$calls[] = 'set:' . $name; }

    /**
     * Observe a native offset removal.
     *
     * @param mixed $name Offset name.
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($name) { self::$calls[] = 'unset:' . $name; }
}
