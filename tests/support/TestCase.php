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
        $this->previousErrorReporting = error_reporting(E_ALL);
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
        set_error_handler(array(self::class, 'handleSourceDiagnostic'));
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
     * Fail every source diagnostic, including diagnostics hidden by @.
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
        $source = strpos($file, PATH_THIRD . 'low_yearly_archives/') !== false
            || strpos($file, PATH_THIRD . 'tests/.coverage-source/') !== false;
        $deprecation = $severity === E_DEPRECATED || $severity === E_USER_DEPRECATED;
        if ($source || $deprecation || (error_reporting() & $severity)) {
            throw new ErrorException(($source ? '' : 'Test support diagnostic: ') . $message, 0, $severity, $file, $line);
        }

        return false;
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
