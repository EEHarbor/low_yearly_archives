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
        $root = dirname(__DIR__, 2) . '/reports/';
        $before = glob($root . 'native-*');
        try {
            LowYearlyNativeProcess::run(array(dirname(__DIR__, 2) . '/fixtures/native-process.php', 'limit'));
        } finally {
            foreach (array_diff(glob($root . 'native-*'), $before) as $directory) {
                $terminal = json_decode(file_get_contents($directory . '/terminal.json'), true, 512, JSON_THROW_ON_ERROR);
                self::assertFalse($terminal['status']['running']);
                self::assertTrue($terminal['status']['signaled'] || $terminal['status']['exitcode'] >= 0);
                if ($terminal['status']['signaled']) {
                    self::assertSame(9, $terminal['status']['termsig']);
                }
            }
        }
    }

    /**
     * Retain exact healthy bytes and both process outcomes after transient cleanup.
     *
     * @param string $mode Owned native output fixture.
     * @param string $expected Exact binary stdout.
     * @return void
     * @dataProvider healthyOutputs
     */
    public function testRetainsEveryHealthyCapture($mode, $expected): void
    {
        $root = dirname(__DIR__, 2) . '/reports/';
        $before = glob($root . 'native-*');
        $result = LowYearlyNativeProcess::run(array(dirname(__DIR__, 2) . '/fixtures/native-process.php', $mode));
        $created = array_values(array_diff(glob($root . 'native-*'), $before));

        self::assertSame(array(0, $expected, ''), $result);
        self::assertCount(2, $created);
        $kinds = array();
        foreach ($created as $directory) {
            $start = json_decode(file_get_contents($directory . '/start.json'), true, 512, JSON_THROW_ON_ERROR);
            $end = json_decode(file_get_contents($directory . '/terminal.json'), true, 512, JSON_THROW_ON_ERROR);
            $kinds[] = $start['kind'];
            self::assertSame(getmypid(), $start['owner_pid']);
            self::assertGreaterThan(0, $end['status']['pid']);
            self::assertFalse($end['status']['running']);
            self::assertNull($end['failure']);
            self::assertSame('', file_get_contents($directory . '/stderr.bin'));
            self::assertSame($end['streams_sha256']['stdout.bin'], hash_file('sha256', $directory . '/stdout.bin'));
            if ($start['kind'] === 'execution') {
                self::assertSame($expected, file_get_contents($directory . '/stdout.bin'));
            }
        }
        sort($kinds);
        self::assertSame(array('configuration', 'execution'), $kinds);
    }

    /**
     * Provide non-text and empty healthy outputs without allowing diagnostics.
     *
     * @return array
     */
    public static function healthyOutputs(): array
    {
        return array('binary' => array('binary', "\x00\xffowned\n"), 'empty' => array('empty', ''));
    }
}
