<?php

final class NativeLoadingTest extends LowYearlyTestCase
{
    /**
     * Compile the loose add-on sources in fresh processes with E_ALL.
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
        );
        self::assertCount(4, $files);
        foreach ($files as $file) {
            list($status, $stdout, $stderr) = $this->process(array('-l', $file));
            self::assertSame(0, $status, $file . ': ' . $stdout . $stderr);
            self::assertSame('', $stderr, $file);
            self::assertStringStartsWith('No syntax errors detected in ', $stdout);
            self::assertDoesNotMatchRegularExpression('/(Deprecated|Warning|Notice|Fatal error)/', $stdout);
        }
    }

    /**
     * Verify the loose plugin rejects direct access.
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
     * Supply the loose plugin guard and its observable response.
     *
     * @return array
     */
    public static function loaderGuards(): array
    {
        return array(
            array('guard', 'No direct script access allowed'),
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
