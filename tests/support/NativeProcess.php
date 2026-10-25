<?php

final class LowYearlyNativeProcess
{
    /**
     * Run the selected PHP runtime with bounded output and an isolated temporary tree.
     *
     * @param array $arguments Existing PHP file, optional lint/build flags and script arguments.
     * @param string|null $sourceBytes
     * @param array $environmentOverrides
     * @return array
     * @throws RuntimeException
     * @throws ErrorException When an unsupported command or unexpected child outcome is retained.
     */
    public static function run(array $arguments, $sourceBytes = null, array $environmentOverrides = array()): array
    {
        $temporaryRoot = getenv('LOW_YEARLY_SUITE_TEMP') ?: sys_get_temp_dir();
        $temporary = $temporaryRoot . '/low-yearly-native-' . bin2hex(random_bytes(8));
        mkdir($temporary, 0700);
        $streams = array();
        $process = null;
        $failure = null;
        $nativeExit = null;
        $signal = null;
        $output = '';
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($trace[1]) ? $trace[1] : array();
        $ownedControl = isset($trace[0]['file'], $caller['class'], $caller['function'])
            && $trace[0]['file'] === dirname(__DIR__) . '/tests/Unit/NativeProcessTest.php'
            && $caller['class'] === 'NativeProcessTest' && $sourceBytes === null && $environmentOverrides === array()
            && $arguments === array(dirname(__DIR__) . '/fixtures/native-process.php', $caller['function'] === 'testCapturesNativeResult' ? 'transport' : 'limit');
        try {
            if ($sourceBytes !== null) {
                $source = $temporary . '/archived-source.php';
                file_put_contents($source, $sourceBytes);
                if (hash('sha256', $sourceBytes) !== hash_file('sha256', $source)) {
                    throw new RuntimeException('Native source copy failed hash verification.');
                }
                $arguments = array_map(function ($argument) use ($source) { return $argument === '{source}' ? $source : $argument; }, $arguments);
            }
            $fileIndex = self::checkArguments($arguments);
            $environment = array_merge(getenv(), $environmentOverrides);
            $environment['TMPDIR'] = $temporary;
            $options = array(PHP_BINARY);
            if (getenv('LOW_YEARLY_RAW_COVERAGE') && $sourceBytes === null && isset($arguments[0]) && realpath($arguments[0]) === __DIR__ . '/native.php') {
                $suffix = PHP_MAJOR_VERSION . PHP_MINOR_VERSION;
                $options = array_merge($options, array('-d', 'zend_extension=/Applications/Herd.app/Contents/Resources/xdebug/xdebug-' . $suffix . '-arm64.so', '-d', 'xdebug.mode=coverage'));
                $environment['LOW_YEARLY_RAW_COVERAGE'] = dirname(getenv('LOW_YEARLY_RAW_COVERAGE')) . '/native-child-' . bin2hex(random_bytes(8)) . '.json';
            } else {
                unset($environment['LOW_YEARLY_RAW_COVERAGE']);
            }
            array_splice($arguments, $fileIndex, 0, array('-d', 'error_reporting=-1', '-d', 'display_errors=stderr', '-d', 'display_startup_errors=1', '-d', 'log_errors=0', '-d', 'sys_temp_dir=' . $temporary, '-d', 'auto_prepend_file=' . __DIR__ . '/NativePrepend.php'));
            foreach (array('out', 'err') as $label) {
                $stream = fopen($temporary . '/' . $label, 'w+b');
                if ($stream === false) {
                    throw new RuntimeException('Could not allocate native process output.');
                }
                $streams[] = $stream;
            }
            $process = proc_open(array_merge($options, $arguments), array(0 => array('pipe', 'r'), 1 => $streams[0], 2 => $streams[1]), $pipes, PATH_THIRD, $environment);
            if (!is_resource($process)) {
                throw new RuntimeException('Could not start native PHP process.');
            }
            fclose($pipes[0]);
            $deadline = microtime(true) + 15;
            do {
                $status = proc_get_status($process);
                if (fstat($streams[0])['size'] > 131072 || fstat($streams[1])['size'] > 131072) {
                    throw new RuntimeException('Native process exceeded its 128-KiB output limit.');
                }
                if (!$status['running']) {
                    break;
                }
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('Native process exceeded its 15-second deadline.');
                }
                usleep(10000);
            } while (true);
            $exit = proc_close($process);
            $process = null;
            rewind($streams[0]);
            rewind($streams[1]);
            $output = stream_get_contents($streams[0]);
            $nativeExit = $exit === -1 ? $status['exitcode'] : $exit;
            $signal = $status['termsig'];

            return array($nativeExit, $output, stream_get_contents($streams[1]));
        } catch (Throwable $error) {
            $failure = $error->getMessage();
            throw $error;
        } finally {
            if (is_resource($process)) {
                proc_terminate($process, 9);
                proc_close($process);
            }
            $errors = '';
            if (isset($streams[1])) {
                rewind($streams[1]);
                $errors = stream_get_contents($streams[1]);
            }
            try {
                foreach ($streams as $stream) { fclose($stream); }
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($files as $file) {
                    if ($file->isDir() && !$file->isLink()) {
                        rmdir($file->getPathname());
                        continue;
                    }
                    unlink($file->getPathname());
                }
                rmdir($temporary);
            } finally {
                $transportFixture = $ownedControl && $caller['function'] === 'testCapturesNativeResult'
                    && $nativeExit === 3 && $signal === 0 && $output === 'out' && $errors === 'err' && $failure === null;
                $limitFixture = $ownedControl && $caller['function'] === 'testRejectsExcessiveOutput'
                    && $failure === 'Native process exceeded its 128-KiB output limit.' && $errors === '';
                if (!$transportFixture && !$limitFixture && ($errors !== '' || $failure !== null || $nativeExit !== 0 || $signal !== 0)) {
                    $receipt = array('exit' => $nativeExit, 'signal' => $signal, 'failure' => $failure, 'stderr' => $errors);
                    $message = $errors !== '' ? 'Native stderr: ' . $errors : 'Unexpected native outcome: ' . json_encode($receipt, JSON_INVALID_UTF8_SUBSTITUTE);
                    LowYearlyDiagnostics::reject(E_WARNING, $message, __FILE__, __LINE__);
                }
            }
        }
    }

    /**
     * Admit file and lint modes whose diagnostics have an owned process boundary.
     *
     * @param array $arguments Native options followed by an existing PHP file.
     * @return int Index of the first file argument.
     * @throws RuntimeException When a command is outside the owned file/lint/build forms.
     */
    private static function checkArguments(array $arguments): int
    {
        $count = count($arguments);
        for ($index = 0; $index < $count; $index++) {
            $argument = $arguments[$index];
            if ($argument === '-l') {
                continue;
            }
            if ($argument === '-d' && isset($arguments[$index + 1]) && is_string($arguments[$index + 1])) {
                $option = $arguments[++$index];
                if ($option !== 'phar.readonly=0') {
                    throw new RuntimeException('The owned native adapter accepts only the explicit PHAR build option.');
                }
                continue;
            }
            if (is_string($argument) && $argument !== '' && $argument[0] !== '-' && is_file($argument)) {
                return $index;
            }

            throw new RuntimeException('The owned native adapter requires an existing PHP file or lint target.');
        }

        throw new RuntimeException('The owned native adapter requires a PHP file.');
    }
}
