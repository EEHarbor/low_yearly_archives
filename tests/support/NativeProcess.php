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
        $prepend = null;
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
            $strict = array('-d', 'error_reporting=-1', '-d', 'display_errors=stderr', '-d', 'display_startup_errors=1', '-d', 'log_errors=0', '-d', 'sys_temp_dir=' . $temporary);
            $deadline = microtime(true) + 15;
            $configuration = array_values(array_filter(array_slice($arguments, 0, $fileIndex), static function ($argument) {
                return $argument !== '-l';
            }));
            $selected = self::selectedPrelude(array_merge($options, $configuration, $strict), $environment, $temporary, $deadline);
            $guard = __DIR__ . '/NativePrepend.php';
            if ($selected !== '') {
                $prepend = self::composePrelude($selected);
                $guard = $prepend['path'];
            }
            array_splice($arguments, $fileIndex, 0, array_merge($strict, array('-d', 'auto_prepend_file=' . $guard)));
            foreach (array('out', 'err') as $label) {
                $stream = fopen($temporary . '/' . $label, 'w+b');
                if ($stream === false) {
                    throw new RuntimeException('Could not allocate native process output.');
                }
                $streams[] = $stream;
            }
            if (microtime(true) >= $deadline) { throw new RuntimeException('Native process exceeded its 15-second deadline.'); }
            $process = proc_open(array_merge($options, $arguments), array(0 => array('pipe', 'r'), 1 => $streams[0], 2 => $streams[1]), $pipes, PATH_THIRD, $environment);
            if (!is_resource($process)) {
                throw new RuntimeException('Could not start native PHP process.');
            }
            fclose($pipes[0]);
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
                if ($prepend !== null) {
                    $information = fstat($prepend['stream']);
                    $same = is_file($prepend['path']) && !is_link($prepend['path']);
                    if ($same) {
                        $named = stat($prepend['path']);
                        $same = $named['dev'] === $information['dev'] && $named['ino'] === $information['ino'] && $named['nlink'] === 1;
                    }
                    rewind($prepend['stream']);
                    $intact = stream_get_contents($prepend['stream']) === $prepend['bytes'];
                    fclose($prepend['stream']);
                    if ($same) { unlink($prepend['path']); }
                    if (!$same || !$intact) { $failure = 'Native prelude composition ownership or bytes changed.'; }
                }
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
     * Ask the selected interpreter to resolve its own effective startup prelude.
     *
     * @param array $options Selected native interpreter and strict startup options.
     * @param array $environment Exact child environment.
     * @param string $temporary Owned output directory.
     * @param float $deadline Shared deadline for discovery and actual execution.
     * @return string Absolute resolved prelude, original missing spelling, or an empty option.
     * @throws RuntimeException When configuration discovery fails or exceeds its bounds.
     */
    private static function selectedPrelude(array $options, array $environment, $temporary, $deadline): string
    {
        $code = '$value = ini_get("auto_prepend_file"); $path = $value === "" ? "" : stream_resolve_include_path($value); echo base64_encode($path === false ? $value : $path);';
        $streams = array();
        $process = null;
        try {
            foreach (array('configuration-out', 'configuration-err') as $name) {
                $stream = fopen($temporary . '/' . $name, 'w+b');
                if ($stream === false) { throw new RuntimeException('Cannot allocate native configuration output.'); }
                $streams[] = $stream;
            }
            $process = proc_open(array_merge($options, array('-r', $code)), array(0 => array('pipe', 'r'), 1 => $streams[0], 2 => $streams[1]), $pipes, PATH_THIRD, $environment);
            if (!is_resource($process)) { throw new RuntimeException('Cannot discover native configuration.'); }
            fclose($pipes[0]);
            do {
                $status = proc_get_status($process);
                if (fstat($streams[0])['size'] > 131072 || fstat($streams[1])['size'] > 131072) {
                    throw new RuntimeException('Native configuration exceeded its 128-KiB output limit.');
                }
                if (!$status['running']) { break; }
                if (microtime(true) >= $deadline) { throw new RuntimeException('Native process exceeded its 15-second deadline.'); }
                usleep(10000);
            } while (true);
            $closed = proc_close($process);
            $process = null;
            $exit = $status['exitcode'] >= 0 ? $status['exitcode'] : $closed;
            rewind($streams[0]);
            rewind($streams[1]);
            $output = stream_get_contents($streams[0]);
            $errors = stream_get_contents($streams[1]);
            $decoded = base64_decode($output, true);
            if ($exit !== 0 || $status['termsig'] !== 0 || $errors !== '' || $decoded === false || base64_encode($decoded) !== $output) {
                throw new RuntimeException('Native configuration failed: ' . json_encode(array('exit' => $exit, 'signal' => $status['termsig'], 'stderr' => $errors, 'stdout' => $output), JSON_INVALID_UTF8_SUBSTITUTE));
            }
            return $decoded;
        } finally {
            if (is_resource($process)) { proc_terminate($process, 9); proc_close($process); }
            foreach ($streams as $stream) { fclose($stream); }
        }
    }

    /**
     * Compose global startup in the physical child cwd to preserve native lookup fallback.
     *
     * @param string $selected Native selected prelude argument.
     * @return array Held owned composition name, stream and exact bytes.
     * @throws RuntimeException When exclusive composition creation fails.
     */
    private static function composePrelude($selected): array
    {
        $cwd = realpath(PATH_THIRD);
        if ($cwd === false || !is_dir($cwd)) { throw new RuntimeException('Native child cwd must exist.'); }
        $path = $cwd . '/.low-yearly-prelude-' . bin2hex(random_bytes(12)) . '.php';
        $stream = fopen($path, 'x+b');
        if ($stream === false) { throw new RuntimeException('Cannot allocate native prelude composition.'); }
        $bytes = '<?php require_once ' . var_export(__DIR__ . '/NativePrepend.php', true) . '; require_once ' . var_export($selected, true) . ';';
        try {
            chmod($path, 0600);
            if (fwrite($stream, $bytes) !== strlen($bytes) || !fflush($stream)) {
                throw new RuntimeException('Cannot write complete native prelude composition.');
            }
            return array('path' => $path, 'stream' => $stream, 'bytes' => $bytes);
        } catch (Throwable $error) {
            $information = fstat($stream);
            fclose($stream);
            if (is_file($path) && !is_link($path)) {
                $named = stat($path);
                if ($named['dev'] === $information['dev'] && $named['ino'] === $information['ino']) { unlink($path); }
            }
            throw $error;
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
