<?php

final class LowYearlyNativeProcess
{
    /**
     * Run the selected PHP runtime with bounded output and an isolated temporary tree.
     *
     * @param array $arguments
     * @param string|null $sourceBytes
     * @param array $environmentOverrides
     * @return array
     * @throws RuntimeException
     */
    public static function run(array $arguments, $sourceBytes = null, array $environmentOverrides = array()): array
    {
        $temporary = sys_get_temp_dir() . '/low-yearly-native-' . bin2hex(random_bytes(8));
        mkdir($temporary, 0700);
        $streams = array();
        $process = null;
        try {
            if ($sourceBytes !== null) {
                $source = $temporary . '/archived-source.php';
                file_put_contents($source, $sourceBytes);
                if (hash('sha256', $sourceBytes) !== hash_file('sha256', $source)) {
                    throw new RuntimeException('Native source copy failed hash verification.');
                }
                $arguments = array_map(function ($argument) use ($source) { return $argument === '{source}' ? $source : $argument; }, $arguments);
            }
            $environment = array_merge(getenv(), $environmentOverrides);
            $environment['TMPDIR'] = $temporary;
            $options = array(PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=1', '-d', 'display_startup_errors=1', '-d', 'log_errors=0', '-d', 'sys_temp_dir=' . $temporary);
            if (getenv('LOW_YEARLY_RAW_COVERAGE') && $sourceBytes === null && isset($arguments[0]) && realpath($arguments[0]) === __DIR__ . '/native.php') {
                $suffix = PHP_MAJOR_VERSION . PHP_MINOR_VERSION;
                $options = array_merge($options, array('-d', 'zend_extension=/Applications/Herd.app/Contents/Resources/xdebug/xdebug-' . $suffix . '-arm64.so', '-d', 'xdebug.mode=coverage'));
                $environment['LOW_YEARLY_RAW_COVERAGE'] = dirname(getenv('LOW_YEARLY_RAW_COVERAGE')) . '/native-child-' . bin2hex(random_bytes(8)) . '.json';
            } else {
                unset($environment['LOW_YEARLY_RAW_COVERAGE']);
            }
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

            return array($exit === -1 ? $status['exitcode'] : $exit, stream_get_contents($streams[0]), stream_get_contents($streams[1]));
        } finally {
            if (is_resource($process)) {
                proc_terminate($process, 9);
                proc_close($process);
            }
            foreach ($streams as $stream) {
                fclose($stream);
            }
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir() && !$file->isLink()) {
                    rmdir($file->getPathname());
                    continue;
                }
                unlink($file->getPathname());
            }
            rmdir($temporary);
        }
    }
}
