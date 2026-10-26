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
        $capture = null;
        $status = null;
        $closed = null;
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
            $command = array_merge($options, $arguments);
            $capture = self::beginCapture($command, $deadline, 'execution');
            $streams = $capture['streams'];
            if (microtime(true) >= $deadline) { throw new RuntimeException('Native process exceeded its 15-second deadline.'); }
            $process = proc_open($command, array(0 => array('pipe', 'r'), 1 => $streams[0], 2 => $streams[1]), $pipes, PATH_THIRD, $environment);
            if (!is_resource($process)) {
                throw new RuntimeException('Could not start native PHP process.');
            }
            $status = proc_get_status($process);
            self::recordSpawn($capture, $status);
            fclose($pipes[0]);
            do {
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
                $status = proc_get_status($process);
            } while (true);
            $closed = proc_close($process);
            $process = null;
            rewind($streams[0]);
            rewind($streams[1]);
            $output = stream_get_contents($streams[0]);
            $nativeExit = $closed === -1 ? $status['exitcode'] : $closed;
            $signal = $status['termsig'];

            return array($nativeExit, $output, stream_get_contents($streams[1]));
        } catch (Throwable $error) {
            $failure = $error->getMessage();
            throw $error;
        } finally {
            if (is_resource($process)) {
                if ($status === null || $status['running']) {
                    proc_terminate($process, 9);
                    $cleanupDeadline = microtime(true) + 1;
                    do {
                        $status = proc_get_status($process);
                        if (!$status['running']) { break; }
                        usleep(10000);
                    } while (microtime(true) < $cleanupDeadline);
                    if ($status['running']) { $failure = 'Native child terminal status remains unknown after termination.'; }
                }
                $closed = proc_close($process);
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
                if ($capture !== null) {
                    self::finishCapture($capture, $status, $closed, $failure);
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
        $status = null;
        $closed = null;
        $failure = null;
        $capture = self::beginCapture(array_merge($options, array('-r', $code)), $deadline, 'configuration');
        try {
            $streams = $capture['streams'];
            $process = proc_open(array_merge($options, array('-r', $code)), array(0 => array('pipe', 'r'), 1 => $streams[0], 2 => $streams[1]), $pipes, PATH_THIRD, $environment);
            if (!is_resource($process)) { throw new RuntimeException('Cannot discover native configuration.'); }
            $status = proc_get_status($process);
            self::recordSpawn($capture, $status);
            fclose($pipes[0]);
            do {
                if (fstat($streams[0])['size'] > 131072 || fstat($streams[1])['size'] > 131072) {
                    throw new RuntimeException('Native configuration exceeded its 128-KiB output limit.');
                }
                if (!$status['running']) { break; }
                if (microtime(true) >= $deadline) { throw new RuntimeException('Native process exceeded its 15-second deadline.'); }
                usleep(10000);
                $status = proc_get_status($process);
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
        } catch (Throwable $error) {
            $failure = $error->getMessage();
            throw $error;
        } finally {
            if (is_resource($process)) {
                if ($status === null || $status['running']) {
                    proc_terminate($process, 9);
                    $cleanupDeadline = microtime(true) + 1;
                    do {
                        $status = proc_get_status($process);
                        if (!$status['running']) { break; }
                        usleep(10000);
                    } while (microtime(true) < $cleanupDeadline);
                    if ($status['running']) { $failure = 'Native child terminal status remains unknown after termination.'; }
                }
                $closed = proc_close($process);
            }
            self::finishCapture($capture, $status, $closed, $failure);
            foreach ($streams as $stream) { fclose($stream); }
        }
    }

    /**
     * Issue a durable native slot before spawning, outside the disposable child tree.
     *
     * @param array $command Exact interpreter command and literal arguments.
     * @param float $deadline Absolute shared execution deadline.
     * @param string $kind Configuration discovery or actual execution.
     * @return array Held binary streams and issued capture directory.
     * @throws RuntimeException When exclusive evidence allocation fails.
     */
    private static function beginCapture(array $command, $deadline, $kind): array
    {
        $reports = dirname(__DIR__) . '/reports';
        if (is_link($reports) || (file_exists($reports) && !is_dir($reports))) {
            throw new RuntimeException('Native reports must be an owned directory.');
        }
        if (!is_dir($reports) && !mkdir($reports, 0700)) {
            throw new RuntimeException('Cannot allocate native report root.');
        }
        $directory = realpath($reports) . '/native-' . bin2hex(random_bytes(16));
        if (!mkdir($directory, 0700)) {
            throw new RuntimeException('Cannot allocate native capture.');
        }
        $start = array('owner_pid' => getmypid(), 'kind' => $kind, 'cwd' => PATH_THIRD,
            'command_base64' => array_map('base64_encode', $command), 'started' => microtime(true),
            'deadline' => $deadline, 'caller' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
        $bytes = json_encode($start, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
        $file = fopen($directory . '/start.json', 'xb');
        if ($file === false || fwrite($file, $bytes) !== strlen($bytes) || !fclose($file)) {
            throw new RuntimeException('Cannot retain native issued slot.');
        }
        self::captures($directory, array('start.json' => hash('sha256', $bytes)));
        $streams = array();
        foreach (array('stdout.bin', 'stderr.bin') as $name) {
            $stream = fopen($directory . '/' . $name, 'x+b');
            if ($stream === false) {
                throw new RuntimeException('Cannot allocate native capture stream.');
            }
            $streams[] = $stream;
        }

        return array('directory' => $directory, 'streams' => $streams);
    }

    /**
     * Persist post-reap status and exact stream identities before resource cleanup.
     *
     * @param array $capture Parent-issued directory and held output streams.
     * @param array|null $status Last observed native process status, including PID.
     * @param int|null $closed Actual proc_close result, or null when spawning failed.
     * @param string|null $failure Existing native admission or execution failure.
     * @return void
     * @throws RuntimeException When terminal evidence cannot be retained.
     */
    private static function finishCapture(array $capture, ?array $status, $closed, $failure): void
    {
        $directory = $capture['directory'];
        $hashes = array();
        foreach (array('stdout.bin', 'stderr.bin') as $index => $name) {
            fflush($capture['streams'][$index]);
            $hashes[$name] = hash_file('sha256', $directory . '/' . $name);
        }
        $terminal = array('status' => $status, 'proc_close' => $closed, 'failure' => $failure,
            'reaped' => $closed !== null, 'timed_out' => $failure === 'Native process exceeded its 15-second deadline.',
            'completed' => microtime(true), 'streams_sha256' => $hashes);
        $bytes = json_encode($terminal, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
        $file = fopen($directory . '/terminal.json', 'xb');
        if ($file === false || fwrite($file, $bytes) !== strlen($bytes) || !fclose($file)) {
            throw new RuntimeException('Cannot retain native terminal outcome.');
        }
        $hashes['terminal.json'] = hash('sha256', $bytes);
        self::captures($directory, $hashes, true);
    }

    /**
     * Bind the native PID before waiting so interrupted attempts remain identifiable.
     *
     * @param array $capture Parent-issued directory and held streams.
     * @param array $status Initial native proc_get_status observation.
     * @return void
     * @throws RuntimeException When the spawn observation cannot be retained.
     */
    private static function recordSpawn(array $capture, array $status): void
    {
        $bytes = json_encode($status, JSON_THROW_ON_ERROR) . "\n";
        $file = fopen($capture['directory'] . '/spawn.json', 'xb');
        if ($file === false || fwrite($file, $bytes) !== strlen($bytes) || !fclose($file)) {
            throw new RuntimeException('Cannot retain native spawn identity.');
        }
        self::captures($capture['directory'], array('spawn.json' => hash('sha256', $bytes)));
    }

    /**
     * Join every issued capture at process shutdown, even after ignored or caught results.
     *
     * @param string $directory Parent-issued immutable evidence directory.
     * @param array $hashes Expected hashes established by the parent writer.
     * @param bool $complete Whether the terminal record was persisted.
     * @return void
     */
    private static function captures($directory, array $hashes, $complete = false): void
    {
        static $issued = array();
        static $registered = false;
        if (!$registered) {
            $registered = true;
            register_shutdown_function(static function () use (&$issued) {
                foreach ($issued as $path => $slot) {
                    if (!$slot['complete']) {
                        LowYearlyDiagnostics::reject(E_WARNING, 'Native capture has no terminal outcome: ' . $path, __FILE__, __LINE__);
                    }
                    foreach ($slot['hashes'] as $name => $digest) {
                        $file = $path . '/' . $name;
                        if (is_link($file) || !is_file($file) || hash_file('sha256', $file) !== $digest) {
                            LowYearlyDiagnostics::reject(E_WARNING, 'Native capture bytes changed: ' . $file, __FILE__, __LINE__);
                        }
                    }
                }
            });
        }
        $prior = isset($issued[$directory]) ? $issued[$directory]['hashes'] : array();
        $issued[$directory] = array('hashes' => array_merge($prior, $hashes), 'complete' => $complete);
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
