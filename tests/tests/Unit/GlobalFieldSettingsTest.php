<?php

use Low\YearlyArchives\FluxCapacitor\Conduit\GlobalFieldSettings;

final class GlobalFieldSettingsTest extends LowYearlyTestCase
{
    /**
     * Supply clean native fractional and signed-word array key forms.
     *
     * @return array
     */
    public static function nativeFloatKeys(): array
    {
        return array(array('positive', 1), array('negative', -1), array('infinity', 0), array('negative-infinity', 0),
            array('nan', 0), array('large', 0), array('negative-large', 0), array('word', PHP_INT_MIN));
    }

    /**
     * Preserve original native array key identity for both reads and writes.
     *
     * @dataProvider nativeFloatKeys
     * @param string $shape Native fractional key shape.
     * @param int $nativeKey Original native signed-word key.
     * @return void
     */
    public function testNativeFloatArrayKeys($shape, $nativeKey): void
    {
        $keys = array('positive' => 1.5, 'negative' => -1.5, 'infinity' => INF, 'negative-infinity' => -INF,
            'nan' => NAN, 'large' => 1.0e100, 'negative-large' => -1.0e100, 'word' => 2.0 ** (PHP_INT_SIZE * 8 - 1));
        $key = $keys[$shape];
        $this->ee->db->rows = array(array('settings' => base64_encode(serialize(array($nativeKey => 'old')))));
        $settings = new GlobalFieldSettings();
        self::assertSame('old', $settings->getSetting($key));
        self::assertTrue($settings->updateSetting($key, 'new'));
        self::assertSame(array('update', array('fieldtypes', array('settings' => base64_encode(serialize(array($nativeKey => 'new')))))), end($this->ee->db->calls));
    }

    /**
     * Preserve the native first byte when a stored string receives a longer value.
     *
     * @return void
     */
    public function testLongStringOffsetValueUsesItsFirstByte(): void
    {
        $this->ee->db->rows = array(array('settings' => base64_encode(serialize('abc'))));
        self::assertTrue((new GlobalFieldSettings())->updateSetting(1, 'long'));
        self::assertSame(array('update', array('fieldtypes', array('settings' => base64_encode(serialize('alc'))))), end($this->ee->db->calls));
    }

    /**
     * Convert a native Stringable value once before storing its first byte.
     *
     * @return void
     */
    public function testStringOffsetValueConvertsOnce(): void
    {
        $value = new NativeOffsetStringValue();
        $this->ee->db->rows = array(array('settings' => base64_encode(serialize('abc'))));
        self::assertTrue((new GlobalFieldSettings())->updateSetting(-1, $value));
        self::assertSame(1, $value->calls);
        self::assertSame(array('update', array('fieldtypes', array('settings' => base64_encode(serialize('abl'))))), end($this->ee->db->calls));
    }

    /**
     * Preserve a native Stringable exception before any database update.
     *
     * @return void
     */
    public function testStringOffsetValueExceptionIsPreserved(): void
    {
        $failure = new \RuntimeException('Native offset conversion failure');
        $value = new NativeOffsetStringValue($failure);
        $this->ee->db->rows = array(array('settings' => base64_encode(serialize('abc'))));
        try {
            (new GlobalFieldSettings())->updateSetting(1, $value);
            self::fail('The native Stringable exception must propagate.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
        self::assertSame(1, $value->calls);
        self::assertSame(array(), array_values(array_filter($this->ee->db->calls, static function ($call) { return $call[0] === 'update'; })));
    }

    /**
     * Leave ArrayAccess fractional keys and object values untouched.
     *
     * @return void
     */
    public function testArrayAccessOffsetsKeepNativeArguments(): void
    {
        NativeOffsetDictionary::$calls = array();
        $this->ee->db->rows = array(array('settings' => base64_encode(serialize(new NativeOffsetDictionary()))));
        $value = new NativeOffsetStringValue();
        $settings = new GlobalFieldSettings();
        self::assertSame('computed', $settings->getSetting(1.5));
        self::assertTrue($settings->updateSetting(1.5, $value));
        self::assertSame(array(array('get', 1.5), array('set', 1.5, $value)), NativeOffsetDictionary::$calls);
        self::assertSame(0, $value->calls);
    }

    /**
     * Preserve direct ArrayAccess writes and the existing object serialization.
     *
     * @return void
     */
    public function testGlobalSettingsRetainNativeArrayAccessWrites(): void
    {
        $wire = base64_encode(serialize(new StoredSettingsArrayAccess()));
        $this->ee->db->rows = array(array('settings' => $wire));
        StoredSettingsArrayAccess::$calls = array();
        self::assertTrue((new GlobalFieldSettings())->updateSetting('missing', 'Z'));
        self::assertSame(array('set:missing'), StoredSettingsArrayAccess::$calls);
        self::assertSame(array('update', array('fieldtypes', array('settings' => $wire))), end($this->ee->db->calls));
    }

    /**
     * Preserve valid native string byte writes and the exact stored serialized shape.
     *
     * @return void
     */
    public function testGlobalSettingsRetainNativeStringByteWrites(): void
    {
        foreach (array(0, '0', 1, -1) as $key) {
            $value = 'stored scalar';
            $value[$key] = 'Z';
            $this->ee->db->rows = array(array('settings' => base64_encode(serialize('stored scalar'))));
            self::assertTrue((new GlobalFieldSettings())->updateSetting($key, 'Z'));
            self::assertSame(array('update', array('fieldtypes', array('settings' => base64_encode(serialize($value))))), end($this->ee->db->calls));
        }
    }

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

class NativeOffsetStringValue
{
    public $calls = 0;
    private $failure;

    /**
     * Select a legitimate callback failure for native conversion parity.
     *
     * @param \RuntimeException|null $failure
     * @return void
     */
    public function __construct($failure = null) { $this->failure = $failure; }

    /**
     * Expose native first-byte conversion and callback ordering.
     *
     * @return string
     * @throws \RuntimeException When the caller supplied a callback failure.
     */
    public function __toString()
    {
        $this->calls++;
        if ($this->failure !== null) { throw $this->failure; }
        return 'long';
    }
}

class NativeOffsetDictionary implements \ArrayAccess
{
    public static $calls = array();

    /**
     * Keep computed keys independent of offset existence.
     *
     * @param mixed $key
     * @return bool
     */
    public function offsetExists($key): bool { self::$calls[] = array('exists', $key); return false; }

    /**
     * Observe the original key and return a computed value.
     *
     * @param mixed $key
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($key) { self::$calls[] = array('get', $key); return 'computed'; }

    /**
     * Observe the original key and value without invoking string conversion.
     *
     * @param mixed $key
     * @param mixed $value
     * @return void
     */
    public function offsetSet($key, $value): void { self::$calls[] = array('set', $key, $value); }

    /**
     * Retain the native dictionary unset interface.
     *
     * @param mixed $key
     * @return void
     */
    public function offsetUnset($key): void { self::$calls[] = array('unset', $key); }
}
