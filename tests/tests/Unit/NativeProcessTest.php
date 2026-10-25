<?php

final class NativeProcessTest extends LowYearlyTestCase
{
    /**
     * Keep exit status, stdout and stderr independent across a fresh native process.
     *
     * @return void
     */
    public function testCapturesNativeResult(): void
    {
        $result = LowYearlyNativeProcess::run(array(dirname(__DIR__, 2) . '/fixtures/native-process.php', 'transport'));

        self::assertSame(array(3, 'out', 'err'), $result);
    }

    /**
     * Preserve the selected default Herd startup effect and actual file entry arguments.
     *
     * @return void
     */
    public function testKeepsConfiguredHerdPrelude(): void
    {
        $file = dirname(__DIR__, 2) . '/fixtures/native-process.php';
        $result = LowYearlyNativeProcess::run(array($file, 'prelude'));
        $value = json_decode($result[1], true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(0, $result[0]);
        self::assertSame('', $result[2]);
        self::assertSame(array('dump-loader.php', 'HerdConfiguration.php'), $value['startup']);
        self::assertSame($file, $value['file']);
        self::assertSame(array($file, 'prelude'), $value['arguments']);
    }

    /**
     * Reject excessive native output before reading it into the parent process.
     *
     * @return void
     */
    public function testRejectsExcessiveOutput(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Native process exceeded its 128-KiB output limit.');

        LowYearlyNativeProcess::run(array(dirname(__DIR__, 2) . '/fixtures/native-process.php', 'limit'));
    }
}
