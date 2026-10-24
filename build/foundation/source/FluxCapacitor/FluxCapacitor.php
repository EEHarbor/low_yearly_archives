<?php

namespace Low\YearlyArchives\FluxCapacitor;

use Low\YearlyArchives\FluxCapacitor\Bridge\EE;

/**
 * Flux Capacitor
 *
 * @package         Flux Capacitor
 * @version         4.10.0
 * @author          Tom Jaeger <Tom@EEHarbor.com>
 * @link            https://eeharbor.com
 * @copyright       Copyright (c) 2018, Tom Jaeger/EEHarbor
 */
class FluxCapacitor extends EE
{
    const L = true;

    public function __construct()
    {
        $params = array("module" => "low_yearly_archives", "module_name" => "Low Yearly Archives");
        parent::__construct($params);
    }
}
