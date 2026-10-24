<?php

final class NativeLoadingTest extends LowYearlyTestCase
{
    /**
     * Cover native legacy property declarations and inherited magic hooks.
     *
     * @return array
     */
    public static function mcpConsumers(): array
    {
        return array(array('bare'), array('public'), array('protected'), array('private'), array('magic'), array('array_access'));
    }

    /**
     * Preserve the original MCP extension contract through actual archived pagination.
     *
     * @dataProvider mcpConsumers
     * @param string $kind Native extension property shape.
     * @return void
     */
    public function testLegacyMcpConsumerPropertiesRemainCompatible($kind): void
    {
        $result = $this->process(array(__DIR__ . '/../../support/native.php', 'mcp-api-' . $kind));
        $this->assertSame(0, $result[0], $result[1] . $result[2]);
        $this->assertSame('', $result[2]);
        $data = $kind === 'bare' ? array() : array('seed' => true);
        if ($kind !== 'private') { $data['pagination'] = 'links'; }
        $this->assertSame(array(
            'error' => $kind === 'private' ? array('Error', 'Cannot access private property LowYearlyNativePrivateMcp::$data') : null,
            'data_type' => $kind === 'array_access' ? 'ArrayObject' : 'array', 'data' => $data,
            'calls' => $kind === 'private' ? array() : array(array('limit', 10), array('offset', 10)),
            'magic' => $kind === 'magic' ? array('get:data') : array(),
        ), json_decode($result[1], true, 512, JSON_THROW_ON_ERROR));
    }

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
        self::assertCount(28, $files);
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
     * Supply root links and POSIX names that must not be traversed or shortened.
     *
     * @return array
     */
    public static function cleanupRoots(): array
    {
        return array(array('existing'), array('existing-slash'), array('dangling'),
            array('dangling-slash'), array('backslash-link'), array('backslash-directory'));
    }

    /**
     * Remove only the requested root while preserving targets and the caller directory.
     *
     * @dataProvider cleanupRoots
     * @param string $kind Owned root shape.
     * @return void
     */
    public function testDirectCleanupPreservesSeparateTargets($kind): void
    {
        $result = $this->process(array(__DIR__ . '/../../support/native.php', 'cleanup-root-' . $kind));
        self::assertSame(0, $result[0], $result[1] . $result[2]);
        self::assertSame(array('target_preserved' => true, 'root_removed' => true, 'cwd_preserved' => true), json_decode($result[1], true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Cover linked extraction roots/containers and stale nested/hidden trees through both entries.
     *
     * @return array
     */
    public static function extractionTrees(): array
    {
        return array(array('direct', 'root'), array('direct', 'container'), array('direct', 'stale'),
            array('fallback', 'root'), array('fallback', 'container'), array('fallback', 'stale'));
    }

    /**
     * Preserve separate targets and the expected working directory during actual extraction.
     *
     * @dataProvider extractionTrees
     * @param string $entry Actual archive entry route.
     * @param string $kind Link or stale extraction-tree shape.
     * @return void
     */
    public function testExtractionPreservesSeparateTargets($entry, $kind): void
    {
        $result = $this->process(array(__DIR__ . '/../../support/native.php', 'extraction-link-' . $entry . '-' . $kind));
        self::assertSame(0, $result[0], $result[1] . $result[2]);
        self::assertSame(array('refused' => $kind !== 'stale', 'target_preserved' => true,
            'link_preserved' => $kind !== 'stale', 'stale_removed' => true, 'cwd_valid' => true,
            'temporary_removed' => true), json_decode($result[1], true, 512, JSON_THROW_ON_ERROR));
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
