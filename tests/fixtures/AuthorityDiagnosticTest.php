<?php


/** @backupStaticAttributes enabled */
final class AuthorityDiagnosticTest extends LowYearlyTestCase
{
    /**
     * Keep caught diagnostics and ignored native failures owned after static restoration.
     *
     * @return void
     */
    public function testOwnedOutcome(): void
    {
        $context = getenv('LOW_YEARLY_AUTHORITY_CONTEXT');
        try {
            if ($context === 'backup') {
                @trigger_error('Owned static-backup diagnostic', E_USER_DEPRECATED);
            }
            if ($context === 'native-exit' || $context === 'generic-caught' || $context === 'forged-transport' || $context === 'closed-child') {
                $mode = $context === 'closed-child' ? 'closed-caught' : ($context === 'native-exit' ? 'nonzero' : ($context === 'generic-caught' ? 'caught' : 'transport'));
                LowYearlyNativeProcess::run(array(__DIR__ . '/native-process.php', $mode));
            }
            if ($context === 'getter-callback') {
                $root = dirname(__DIR__, 2);
                LowYearlyNativeProcess::run(array(dirname(__DIR__) . '/support/native-callee.php', $root . '/low_yearly_archives/low_yearly_archives.phar', $root, 'GFS', 'getter', 'diagnostic'));
            }
            if ($context === 'inline-caught') {
                LowYearlyNativeProcess::run(array('-r', 'try { @trigger_error("Owned inline diagnostic", E_USER_DEPRECATED); } catch (Throwable $error) {}'));
            }
            if ($context === 'caller-prelude') {
                LowYearlyNativeProcess::run(array('-d', 'auto_prepend_file=' . __FILE__, __DIR__ . '/native-process.php', 'settings'));
            }
        } catch (\Throwable $error) {
        }

        self::assertTrue(true);
    }
}
