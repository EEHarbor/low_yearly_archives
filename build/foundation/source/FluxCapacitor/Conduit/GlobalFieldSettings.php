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
        if (\is_array($ftSettings) && $name === null) {
            $name = '';
        }
        if (\is_array($ftSettings) && \is_float($name)) {
            if (!\is_finite($name)) {
                $name = 0;
            } else {
                $range = 2.0 ** (\PHP_INT_SIZE * 8);
                $name = \fmod($name, $range);
                if ($name >= $range / 2) {
                    $name -= $range;
                }
                if ($name < -$range / 2) {
                    $name += $range;
                }
                $name = (int) $name;
            }
        }
        if (\is_array($ftSettings) || \is_string($ftSettings)) {
            return isset($ftSettings[$name]) ? $ftSettings[$name] : null;
        }

        return null;
    }

    /**
     * Load native global fieldtype values and retain the legacy false-like fallback.
     *
     * @return mixed Native stored value, an empty array for false-like values, or null when absent.
     * @throws \Throwable When original namespace hooks or hydration callbacks fail.
     */
    public function getSettings()
    {
        $ftSettingsQuery = ee()->db->select('settings')
            ->where('name', $this->fieldtype_name)
            ->get('fieldtypes');

        if ((bool) $ftSettingsQuery->num_rows()) {
            $stored = $ftSettingsQuery->row('settings');
            $carrierHook = \function_exists(__NAMESPACE__ . '\\base64_decode');
            $parserHook = \function_exists(__NAMESPACE__ . '\\unserialize');
            if ($carrierHook || $parserHook) {
                $decoded = base64_decode($carrierHook ? $stored : ($stored === null ? '' : $stored));
                $ftSettings = $parserHook ? unserialize($decoded) : SerializedData::decode($decoded);
            } else {
                $ftSettings = \is_string($stored) ? SerializedData::decode(base64_decode($stored)) : false;
            }
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

        $stringOffset = false;
        if (\is_string($ftSettings)) {
            $pattern = \PHP_VERSION_ID < 80000 ? '/^\s*[+-]?[0-9]+$/D' : '/^\s*[+-]?[0-9]+\s*$/D';
            $stringOffset = (\is_int($key) || (\is_string($key) && \preg_match($pattern, $key)))
                && $key >= -\strlen($ftSettings) && $key < \PHP_INT_MAX;
        }
        if ($ftSettings === null) {
            return false;
        }
        if (\is_array($ftSettings) && $key === null) {
            $key = '';
        }
        if (\is_array($ftSettings) && \is_float($key)) {
            if (!\is_finite($key)) {
                $key = 0;
            } else {
                $range = 2.0 ** (\PHP_INT_SIZE * 8);
                $key = \fmod($key, $range);
                if ($key >= $range / 2) {
                    $key -= $range;
                }
                if ($key < -$range / 2) {
                    $key += $range;
                }
                $key = (int) $key;
            }
        }
        if ($stringOffset) {
            $value = \is_float($value) && \is_nan($value) ? 'NAN' : (string) $value;
            if (\strlen($value) > 1) {
                $value = $value[0];
            }
        }

        $ftSettings[$key] = $value;
        $settingsForDb = array('settings' => base64_encode(serialize($ftSettings)));

        return ee()->db->where('name', $this->fieldtype_name)
            ->update('fieldtypes', $settingsForDb);
    }
}
