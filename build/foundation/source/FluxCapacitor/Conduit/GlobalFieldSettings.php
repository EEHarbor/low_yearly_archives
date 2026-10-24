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
     * Read an existing global fieldtype setting without missing-key diagnostics.
     *
     * @param string $name Setting key.
     * @return mixed Setting value or null when absent.
     */
    public function getSetting($name)
    {
        $ftSettings = $this->getSettings();
        if ($ftSettings) {
            return isset($ftSettings[$name]) ? $ftSettings[$name] : null;
        }
        return null;
    }

    /**
     * Load global fieldtype settings and normalize invalid legacy payloads.
     *
     * @return array|null Stored setting array, or null when the fieldtype row is absent.
     */
    public function getSettings()
    {
        $ftSettingsQuery = ee()->db->select('settings')
            ->where('name', $this->fieldtype_name)
            ->get('fieldtypes');

        if ((bool) $ftSettingsQuery->num_rows()) {
            $stored = $ftSettingsQuery->row('settings');
            $ftSettings = is_string($stored) ? SerializedData::decode(base64_decode($stored, true)) : false;
            $ftSettings = is_array($ftSettings) ? $ftSettings : array();
        } else {
            return null;
        }

        return $ftSettings;
    }

    /**
     * Persist one global setting and return the actual write result.
     *
     * @param string $key Setting key.
     * @param mixed $value Setting value.
     * @return bool Whether the settings row was successfully updated.
     */
    public function updateSetting($key, $value)
    {
        $ftSettings = $this->getSettings();

        if ($ftSettings === null) {
            return false;
        }

        $ftSettings[$key] = $value;
        $settingsForDb = array('settings' => base64_encode(serialize($ftSettings)));

        return ee()->db->where('name', $this->fieldtype_name)
            ->update('fieldtypes', $settingsForDb);
    }
}
