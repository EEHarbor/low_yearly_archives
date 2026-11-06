<?php

final class ProductionRegressionTest extends LowYearlyTestCase
{
    /**
     * Reject source deprecations for a null helper input on modern PHP versions.
     *
     * @return void
     */
    public function testNullParameterDoesNotEmitSourceDeprecation(): void
    {
        require_once PATH_THIRD . 'low_yearly_archives/pi.low_yearly_archives.php';
        $plugin = (new ReflectionClass(Low_yearly_archives::class))->newInstanceWithoutConstructor();

        self::assertSame(array(array(''), true), $this->invoke($plugin, '_explode_param', array(null)));
    }
}
