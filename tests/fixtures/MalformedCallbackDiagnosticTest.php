<?php

require_once dirname(__DIR__) . '/support/malformed-callback.php';

class MalformedCallbackDiagnosticTest extends LowYearlyTestCase
{
    /**
     * Keep a positive assertion while checked validation rejects caught callback events.
     *
     * @dataProvider values
     * @param bool $value Positive control assertion.
     * @return void
     */
    public function testCaughtMalformedCallback($value): void
    {
        if (getenv('LOW_YEARLY_CAUGHT_CONTEXT') === 'main') {
            MalformedStoredCallback::run();
        }
        if (getenv('LOW_YEARLY_CAUGHT_CONTEXT') === 'native') {
            try {
                LowYearlyNativeProcess::run(array(dirname(__DIR__) . '/support/native.php', 'malformed-callback'));
            } catch (\Throwable $exception) {
            }
        }
        self::assertTrue($value);
    }

    /**
     * Retain valid provider data after the generated callback diagnostic is caught.
     *
     * @return array
     */
    public static function values(): array
    {
        if (getenv('LOW_YEARLY_CAUGHT_CONTEXT') === 'provider') {
            MalformedStoredCallback::run();
        }

        return array(array(true));
    }
}
