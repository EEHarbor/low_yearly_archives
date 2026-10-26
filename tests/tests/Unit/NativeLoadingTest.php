<?php

final class NativeLoadingTest extends LowYearlyTestCase
{
    /**
     * Compile every loose and archived source in fresh processes with E_ALL.
     *
     * @return void
     */
    public function testAllShippedPhpCompilesWithoutDiagnostics(): void
    {
        $files = array(
            PATH_THIRD . 'low_yearly_archives/addon.setup.php',
            PATH_THIRD . 'low_yearly_archives/autoload.php',
            PATH_THIRD . 'low_yearly_archives/pi.low_yearly_archives.php',
            PATH_THIRD . 'low_yearly_archives/views/license.php',
            PATH_THIRD . 'low_yearly_archives/low_yearly_archives.phar',
        );
        $phar = new Phar(PATH_THIRD . 'low_yearly_archives/low_yearly_archives.phar');
        foreach (new RecursiveIteratorIterator($phar) as $entry) {
            if (substr($entry->getPathname(), -4) === '.php') {
                $files[] = $entry->getPathname();
            }
        }
        self::assertCount(27, $files);
        foreach ($files as $file) {
            $archived = strpos($file, 'phar://') === 0;
            list($status, $stdout, $stderr) = $this->process(array('-l', $archived ? '{source}' : $file), $archived ? file_get_contents($file) : null);
            self::assertSame(0, $status, $file . ': ' . $stdout . $stderr);
            self::assertSame('', $stderr, $file);
            self::assertStringStartsWith('No syntax errors detected in ', $stdout);
            self::assertDoesNotMatchRegularExpression('/(Deprecated|Warning|Notice|Fatal error)/', $stdout);
        }
    }

    /**
     * Load every supported bridge dispatcher branch through the original PHAR.
     *
     * @param string $version
     * @param string $parent
     * @return void
     * @dataProvider engineVersions
     */
    public function testNativeBridgeDispatcher($version, $parent): void
    {
        list($status, $stdout, $stderr) = $this->process(array(__DIR__ . '/../../support/native.php', 'loader', $version));

        self::assertSame(0, $status, $stdout . $stderr);
        self::assertSame('', $stderr);
        self::assertSame('Low\\YearlyArchives\\FluxCapacitor\\Bridge\\' . $parent . ':' . $version, $stdout);
    }

    /**
     * Load the source compatibility helper and configured version before EE bootstrap constants.
     *
     * @return void
     */
    public function testNativeLegacyBootstrapDefinesFacadeAndVersion(): void
    {
        list($status, $stdout, $stderr) = $this->process(array(__DIR__ . '/../../support/native.php', 'legacy-bootstrap', '2.11.9'));

        self::assertSame(0, $status, $stdout . $stderr);
        self::assertSame('', $stderr);
        self::assertSame(array(
            'before' => false, 'after' => true, 'facade_matches' => true,
            'version' => '2.11.9', 'bridge' => 'Low\\YearlyArchives\\FluxCapacitor\\Bridge\\EE2',
            'config_calls' => array('app_version'),
        ), json_decode($stdout, true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Exercise direct access, foreign namespaces and explicit missing-file errors.
     *
     * @param string $scenario
     * @param string $expected
     * @return void
     * @dataProvider loaderGuards
     */
    public function testLoaderGuards($scenario, $expected): void
    {
        list($status, $stdout, $stderr) = $this->process(array(__DIR__ . '/../../support/native.php', $scenario));

        self::assertSame(0, $status, $stdout . $stderr);
        self::assertSame('', $stderr);
        self::assertStringContainsString($expected, $stdout);
    }

    /**
     * Exercise the shipped self-extracting loader in an isolated temp directory.
     *
     * @return void
     */
    public function testFallbackLoader(): void
    {
        list($status, $stdout, $stderr) = $this->process(array(__DIR__ . '/../../support/native.php', 'fallback-loader'));

        self::assertSame(0, $status, $stdout . $stderr);
        self::assertSame('', $stderr);
        self::assertSame('Low\\YearlyArchives\\FluxCapacitor\\Bridge\\EE6:7.5.17', $stdout);
    }

    /**
     * Supply all bridge selection versions and the current-major fallback.
     *
     * @return array
     */
    public static function engineVersions(): array
    {
        return array(array('2.11.9', 'EE2'), array('3.5.17', 'EE3'), array('4.3.8', 'EE4'), array('5.4.3', 'EE5'), array('6.4.18', 'EE6'), array('7.5.17', 'EE6'));
    }

    /**
     * Supply guard scenarios and their observable response.
     *
     * @return array
     */
    public static function loaderGuards(): array
    {
        return array(
            array('guard', 'No direct script access allowed'),
            array('unrelated-loader', 'unrelated namespace ignored'),
            array('missing-archive', 'Add-on PHAR file at location '),
            array('missing-class', 'Class at location '),
        );
    }

    /**
     * Run the exact selected PHP binary and restore only this child's temp tree.
     *
     * @param array $arguments
     * @param string|null $sourceBytes
     * @return array
     * @throws RuntimeException
     */
    private function process(array $arguments, $sourceBytes = null): array
    {
        return LowYearlyNativeProcess::run($arguments, $sourceBytes);
    }
}
