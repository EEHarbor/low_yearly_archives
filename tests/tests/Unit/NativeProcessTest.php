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
        $result = LowYearlyNativeProcess::run(array('-r', 'fwrite(STDOUT, "out"); fwrite(STDERR, "err"); exit(3);'));

        self::assertSame(array(3, 'out', 'err'), $result);
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

        LowYearlyNativeProcess::run(array('-r', 'fwrite(STDOUT, str_repeat("x", 131073));'));
    }
}
