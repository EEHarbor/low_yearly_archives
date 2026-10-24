<?php

final class CaughtDiagnosticTest extends LowYearlyTestCase
{
    /**
     * Catch generated diagnostics while the actual checked owner retains them.
     *
     * @dataProvider values
     * @param bool $value Positive control value.
     * @return void
     */
    public function testCaughtDiagnostic($value): void
    {
        try {
            if (getenv('LOW_YEARLY_CAUGHT_CONTEXT') === 'main') {
                @trigger_error('Low Yearly caught main sentinel', E_USER_DEPRECATED);
            }
            if (strpos(getenv('LOW_YEARLY_CAUGHT_CONTEXT') ?: '', 'native') === 0) {
                $scenario = str_replace('native', 'caught-diagnostic', getenv('LOW_YEARLY_CAUGHT_CONTEXT'));
                LowYearlyNativeProcess::run(array(__DIR__ . '/../support/native.php', $scenario));
            }
        } catch (Throwable $error) {
        }
        self::assertTrue($value);
    }

    /**
     * Keep provider input valid after a generated caught suppressed diagnostic.
     *
     * @return array
     */
    public static function values(): array
    {
        if (getenv('LOW_YEARLY_CAUGHT_CONTEXT') === 'provider') {
            try {
                @trigger_error('Low Yearly caught provider sentinel', E_USER_DEPRECATED);
            } catch (ErrorException $error) {
            }
        }
        return array(array(true));
    }
}
