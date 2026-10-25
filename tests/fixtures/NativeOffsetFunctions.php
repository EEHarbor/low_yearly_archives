<?php

namespace Low\YearlyArchives\FluxCapacitor\Conduit {
    const PHP_INT_SIZE = 1;
    const PHP_INT_MAX = 3;

    /**
     * Model an unrelated consumer function that must not replace native arithmetic.
     *
     * @param float $number Input value.
     * @param float $modulus Native modulus.
     * @return float
     */
    function fmod($number, $modulus)
    {
        \NativeOffsetFunctionState::$calls++;
        return 0.0;
    }
}

namespace  {
    class NativeOffsetFunctionState
    {
        public static $calls = 0;
    }
}
