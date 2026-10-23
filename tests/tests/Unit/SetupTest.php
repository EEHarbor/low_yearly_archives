<?php

final class SetupTest extends LowYearlyTestCase
{
    /**
     * Verify package metadata comes from the shipped JSON manifest.
     *
     * @return void
     */
    public function testSetupMatchesManifest(): void
    {
        $setup = require PATH_THIRD . 'low_yearly_archives/addon.setup.php';
        $manifest = json_decode(file_get_contents(PATH_THIRD . 'low_yearly_archives/addon.json'), true);

        self::assertSame($manifest['name'], $setup['name']);
        self::assertSame($manifest['version'], $setup['version']);
        self::assertSame($manifest['description'], $setup['description']);
        self::assertSame($manifest['namespace'], $setup['namespace']);
        self::assertSame(false, $setup['settings_exist']);
        self::assertSame('EEHarbor', $setup['author']);
        self::assertSame('http://eeharbor.com/low_yearly_archives/documentation', $setup['docs_url']);
    }

    /**
     * Ensure the diagnostic gate catches every suppressed source diagnostic.
     *
     * @param int $severity
     * @return void
     * @dataProvider sourceDiagnostics
     */
    public function testDiagnosticGateRejectsSuppressedSourceDiagnostics($severity): void
    {
        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('diagnostic sentinel');
        @LowYearlyTestCase::handleSourceDiagnostic($severity, 'diagnostic sentinel', PATH_THIRD . 'low_yearly_archives/autoload.php', 1);
    }

    /**
     * Supply warning, notice and deprecation severities enforced by the campaign.
     *
     * @return array
     */
    public static function sourceDiagnostics(): array
    {
        return array(array(E_WARNING), array(E_NOTICE), array(E_DEPRECATED), array(E_USER_DEPRECATED));
    }

    /**
     * Keep support diagnostics distinct from failures originating in shipped code.
     *
     * @return void
     */
    public function testSupportDeprecationIsAttributedAndFails(): void
    {
        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Test support diagnostic: support sentinel');
        @LowYearlyTestCase::handleSourceDiagnostic(E_USER_DEPRECATED, 'support sentinel', __FILE__, __LINE__);
    }
}
