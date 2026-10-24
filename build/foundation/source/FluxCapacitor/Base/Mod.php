<?php
namespace Low\YearlyArchives\FluxCapacitor\Base;

use Low\YearlyArchives\FluxCapacitor\FluxCapacitor;

/**
 * EEHarbor mod parent class
 *
 * @package         EEHarbor Module Parent
 * @version         4.10.0
 * @author          Tom Jaeger <Tom@EEHarbor.com>
 * @link            https://eeharbor.com
 * @copyright       Copyright (c) 2018, Tom Jaeger/EEHarbor
 */

class Mod
{
    public $flux;
    public $version;

    public function __construct()
    {
        $this->flux = new FluxCapacitor;
        $this->version = $this->flux->getConfig('version');
    }

    /**
     * Read a template parameter while preserving literal zero content.
     *
     * @param string $parameter Parameter name.
     * @param mixed $fallback Value returned when the parameter is absent or empty.
     * @param bool $trim Whether to normalize surrounding whitespace.
     * @return mixed Normalized parameter or fallback.
     */
    protected function getParameter($parameter, $fallback = null, $trim=true)
    {
        $val = ee()->TMPL->fetch_param($parameter);

        // Trim the whitespaces on the parameter, to prevent a lot of potential headache
        if($trim) {
            $val = trim(str_replace(array('&nbsp;', '&#32;'), array(' ', ' '), $val));
        }

        if ($val === '' || $val === false || $val === null) {
            return $fallback;
        }

        return $val;
    }
}
