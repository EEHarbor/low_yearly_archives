<?php

final class LowYearlyBranchCoverage
{
    private static $coverage = array();
    private static $registered = false;

    /**
     * Start direct Xdebug collection independently of PHPUnit's Clover driver.
     *
     * @return void
     */
    public static function start(): void
    {
        if (!getenv('LOW_YEARLY_RAW_COVERAGE')) {
            return;
        }
        if (!function_exists('xdebug_start_code_coverage') || xdebug_code_coverage_started()) {
            throw new RuntimeException('Raw branch collection requires idle Xdebug coverage instrumentation.');
        }
        if (!self::$registered) {
            register_shutdown_function(array(self::class, 'write'));
            self::$registered = true;
        }
        xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE | XDEBUG_CC_BRANCH_CHECK);
    }

    /**
     * Merge reached basic blocks and exits only within this process's opcode graph.
     *
     * @return void
     */
    public static function stop(): void
    {
        if (!getenv('LOW_YEARLY_RAW_COVERAGE')) {
            return;
        }
        $current = xdebug_get_code_coverage();
        xdebug_stop_code_coverage();
        foreach ($current as $file => $coverage) {
            $root = dirname(__DIR__, 2) . '/';
            $source = strpos($file, $root . 'low_yearly_archives/') !== false
                || strpos($file, $root . 'tests/.coverage-source/') !== false;
            if (!$source) {
                continue;
            }
            foreach ($coverage['lines'] as $line => $hit) {
                self::$coverage[$file]['lines'][$line] = max(self::$coverage[$file]['lines'][$line] ?? -2, $hit);
            }
            foreach ($coverage['functions'] as $function => $data) {
                foreach ($data['branches'] as $index => $branch) {
                    $previous = self::$coverage[$file]['functions'][$function]['branches'][$index] ?? null;
                    if ($previous !== null) {
                        if ($previous['out'] !== $branch['out'] || $previous['op_end'] !== $branch['op_end']) {
                            throw new RuntimeException('Incompatible compiled branch graph: ' . $file . ':' . $function);
                        }
                        $branch['hit'] += $previous['hit'];
                        foreach ($branch['out_hit'] as $exit => $count) {
                            $branch['out_hit'][$exit] += $previous['out_hit'][$exit];
                        }
                    }
                    self::$coverage[$file]['functions'][$function]['branches'][$index] = $branch;
                }
                foreach ($data['paths'] as $index => $path) {
                    $previous = self::$coverage[$file]['functions'][$function]['paths'][$index] ?? null;
                    if ($previous !== null) {
                        if ($previous['path'] !== $path['path']) {
                            throw new RuntimeException('Incompatible compiled path graph: ' . $file . ':' . $function);
                        }
                        $path['hit'] += $previous['hit'];
                    }
                    self::$coverage[$file]['functions'][$function]['paths'][$index] = $path;
                }
            }
        }
    }

    /**
     * Save graph provenance and raw out/out_hit data without implying full coverage.
     *
     * @return void
     */
    public static function write(): void
    {
        $scenario = basename($GLOBALS['argv'][0] ?? '') === 'native.php' ? ($GLOBALS['argv'][1] ?? null) : 'suite';
        $report = array('php' => PHP_VERSION, 'xdebug' => phpversion('xdebug'), 'pid' => getmypid(), 'created' => microtime(true), 'ee' => APP_VER, 'request' => REQ, 'scenario' => $scenario, 'files' => array());
        foreach (self::$coverage as $file => $coverage) {
            $report['files'][$file] = array('sha256' => hash_file('sha256', $file), 'coverage' => $coverage);
        }
        file_put_contents(getenv('LOW_YEARLY_RAW_COVERAGE'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
}
