<?php

namespace Low\YearlyArchives\FluxCapacitor\Conduit;

use Low\YearlyArchives\FluxCapacitor\FluxCapacitor;

class GlobalFieldSettings
{
    public $flux;
    public $fieldtype_name;
    public function __construct($fieldtype_name=null)
    {
        $this->flux = new FluxCapacitor;
        $this->fieldtype_name = $fieldtype_name ?: $this->flux->shortname;
    }

    /**
     * Read native array keys and string offsets without changing ArrayAccess dispatch.
     *
     * @param mixed $name Setting key or native byte offset.
     * @return mixed Setting value or null when no ordinary offset is present.
     * @throws \Throwable When a native ArrayAccess callback fails.
     */
    public function getSetting($name)
    {
        $ftSettings = $this->getSettings();
        if ($ftSettings instanceof \ArrayAccess) {
            return $ftSettings[$name];
        }
        if (is_array($ftSettings) && is_float($name)) {
            $name = self::nativeArrayKey($name);
        }
        if (is_array($ftSettings) || is_string($ftSettings)) {
            return isset($ftSettings[$name]) ? $ftSettings[$name] : null;
        }

        return null;
    }

    /**
     * Load native global fieldtype values and retain the legacy false-like fallback.
     *
     * @return mixed Native stored value, an empty array for false-like values, or null when absent.
     */
    public function getSettings()
    {
        $ftSettingsQuery = ee()->db->select('settings')
            ->where('name', $this->fieldtype_name)
            ->get('fieldtypes');

        if ((bool) $ftSettingsQuery->num_rows()) {
            $stored = $ftSettingsQuery->row('settings');
            $ftSettings = is_string($stored) ? SerializedData::decode(base64_decode($stored)) : false;
            $ftSettings = $ftSettings ?: array();
        } else {
            return null;
        }

        return $ftSettings;
    }

    /**
     * Persist a native array, ArrayAccess value or writable string byte.
     *
     * @param mixed $key Setting key or native byte offset.
     * @param mixed $value Setting value.
     * @return bool Whether the settings row was successfully updated.
     * @throws \Throwable When native conversion, serialization or a callback fails.
     */
    public function updateSetting($key, $value)
    {
        $ftSettings = $this->getSettings();

        if (!is_array($ftSettings) && !$ftSettings instanceof \ArrayAccess
            && !self::canWriteStringOffset($ftSettings, $key)) {
            return false;
        }
        if (is_array($ftSettings) && is_float($key)) {
            $key = self::nativeArrayKey($key);
        }
        if (is_string($ftSettings)) {
            $value = (string) $value;
            if (strlen($value) > 1) {
                $value = $value[0];
            }
        }

        $ftSettings[$key] = $value;
        $settingsForDb = array('settings' => base64_encode(serialize($ftSettings)));

        return ee()->db->where('name', $this->fieldtype_name)
            ->update('fieldtypes', $settingsForDb);
    }

    /**
     * Preserve native signed-word array keys without newer float-cast diagnostics.
     *
     * @param float $key
     * @return int
     */
    private static function nativeArrayKey($key)
    {
        if (!is_finite($key)) {
            return 0;
        }

        $range = 2.0 ** (PHP_INT_SIZE * 8);
        $key = fmod($key, $range);
        if ($key >= $range / 2) {
            $key -= $range;
        }
        if ($key < -$range / 2) {
            $key += $range;
        }

        return (int) $key;
    }

    /**
     * Accept native integer string offsets without coercing invalid keys.
     *
     * @param mixed $settings
     * @param mixed $key
     * @return bool
     */
    private static function canWriteStringOffset($settings, $key)
    {
        if (!is_string($settings)) {
            return false;
        }

        $pattern = PHP_VERSION_ID < 80000 ? '/^\s*[+-]?[0-9]+$/D' : '/^\s*[+-]?[0-9]+\s*$/D';
        if (!is_int($key) && !(is_string($key) && preg_match($pattern, $key))) {
            return false;
        }

        return $key >= -strlen($settings) && $key < PHP_INT_MAX;
    }
}
