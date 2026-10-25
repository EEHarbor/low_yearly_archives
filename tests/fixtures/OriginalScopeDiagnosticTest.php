<?php

require_once dirname(__DIR__) . '/support/original-scope-callback.php';

class OriginalScopeDiagnosticTest extends LowYearlyTestCase
{
    /**
     * Keep a positive case while checked validation rejects the caught source event.
     *
     * @dataProvider values
     * @param bool $value Positive control assertion.
     * @return void
     */
    public function testCaughtOriginalScopeCallback($value): void
    {
        if (getenv('LOW_YEARLY_CAUGHT_CONTEXT') === 'main') { OriginalScopeStoredCallback::run(); }
        if (getenv('LOW_YEARLY_CAUGHT_CONTEXT') === 'native') {
            try { LowYearlyNativeProcess::run(array(dirname(__DIR__) . '/support/native.php', 'original-scope-callback')); } catch (\Throwable $exception) {}
        }
        self::assertTrue($value);
    }

    /**
     * Retain valid provider output after an original-scope callback catches its event.
     *
     * @return array
     */
    public static function values(): array
    {
        if (getenv('LOW_YEARLY_CAUGHT_CONTEXT') === 'provider') { OriginalScopeStoredCallback::run(); }

        return array(array(true));
    }
}
