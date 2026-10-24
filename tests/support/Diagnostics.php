<?php

final class LowYearlyDiagnostics
{
    private static $events = array();

    /**
     * Retain every PHP diagnostic before a caller can catch its exception.
     *
     * @return void
     */
    public static function install(): void
    {
        error_reporting(-1);
        set_error_handler(static function ($severity, $message, $file, $line) {
            self::reject($severity, $message, $file, $line);
        }, E_ALL);
    }

    /**
     * Format the same source/support exception used by manual severity vectors.
     *
     * @param int $severity Diagnostic severity.
     * @param string $message Diagnostic text.
     * @param string $file Diagnostic origin.
     * @param int $line Diagnostic line.
     * @return ErrorException
     */
    public static function exception($severity, $message, $file, $line): ErrorException
    {
        $root = dirname(__DIR__, 2);
        $source = strpos($file, $root . '/low_yearly_archives/') !== false
            || strpos($file, $root . '/tests/.coverage-source/') !== false;

        return new ErrorException(($source ? '' : 'Test support diagnostic: ') . $message, 0, $severity, $file, $line);
    }

    /**
     * Journal a diagnostic before throwing through source or test catch blocks.
     *
     * @param int $severity Diagnostic severity.
     * @param string $message Diagnostic text or captured child stderr.
     * @param string $file Diagnostic origin.
     * @param int $line Diagnostic line.
     * @return void
     * @throws ErrorException After recording the event.
     */
    public static function reject($severity, $message, $file, $line): void
    {
        $error = self::exception($severity, $message, $file, $line);
        $event = array('severity' => $severity, 'message' => $error->getMessage(), 'file' => $file, 'line' => $line);
        self::$events[] = $event;
        fwrite(STDERR, 'LOW_YEARLY_DIAGNOSTIC:' . json_encode($event, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
        throw $error;
    }

    /**
     * Return retained diagnostics for the checked process owner.
     *
     * @return array
     */
    public static function events(): array
    {
        return self::$events;
    }
}

if (error_reporting() !== -1 || ini_get('display_errors') !== 'stderr' || ini_get('display_startup_errors') !== '1') {
    throw new RuntimeException('Low Yearly Archives requires strict diagnostics before bootstrap.');
}

LowYearlyDiagnostics::install();
