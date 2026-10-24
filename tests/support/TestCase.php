<?php

use Low\YearlyArchives\FluxCapacitor\Conduit\StaticCache;

abstract class LowYearlyTestCase extends PHPUnit\Framework\TestCase
{
    protected $ee;
    private $previousErrorReporting;

    /**
     * Reset the EE request and enforce diagnostics from shipped code.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->previousErrorReporting = error_reporting(-1);
        $this->ee = (object) array(
            'TMPL' => new LowYearlyTemplate(),
            'db' => new LowYearlyDatabase(),
            'settings_db' => new LowYearlyDatabase(),
            'config' => new LowYearlyConfig(),
            'localize' => new LowYearlyLocalize(),
            'lang' => new LowYearlyLanguage(),
            'session' => new LowYearlySession(),
            'alert' => new LowYearlyAlert(),
            'input' => new LowYearlyInput(),
            'uri' => new LowYearlyUri(),
            'services' => array(),
            'service_calls' => array(),
        );
        $GLOBALS['low_yearly_ee'] = $this->ee;
        LowYearlyDiagnostics::install();
        LowYearlyBranchCoverage::start();
        StaticCache::clear();
    }

    /**
     * Restore PHP and EE globals even when a source diagnostic fails a test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        StaticCache::clear();
        LowYearlyBranchCoverage::stop();
        restore_error_handler();
        error_reporting($this->previousErrorReporting);
        unset($GLOBALS['low_yearly_ee']);
    }

    /**
     * Throw the handler's exception format for manual generated severity vectors.
     *
     * @param int $severity
     * @param string $message
     * @param string $file
     * @param int $line
     * @return bool
     * @throws ErrorException
     */
    public static function handleSourceDiagnostic($severity, $message, $file, $line)
    {
        throw LowYearlyDiagnostics::exception($severity, $message, $file, $line);
    }

    /**
     * Invoke one private/protected source method with real fixture inputs.
     *
     * @param object|string $object
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    protected function invoke($object, $method, array $arguments = array())
    {
        $reflection = new ReflectionMethod($object, $method);
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }

        return $reflection->invokeArgs(is_object($object) ? $object : null, $arguments);
    }
}
