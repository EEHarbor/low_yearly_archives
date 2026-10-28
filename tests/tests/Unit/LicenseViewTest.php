<?php

final class LicenseViewTest extends LowYearlyTestCase
{
    /**
     * Render the add-on license view with EE2 and modern alert behavior.
     *
     * @param string $view
     * @param int $major
     * @param string $license
     * @param bool $ignored
     * @return void
     * @dataProvider viewContexts
     */
    public function testLicenseView($view, $major, $license, $ignored): void
    {
        $ee_ver = $major;
        $license_key = $license;
        $ignore_site = $ignored;
        $action_url = '/admin?save_license';
        ob_start();
        try {
            include $view;
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertStringContainsString('box ee' . $major . ' addon-license', $html);
        self::assertStringContainsString('<form action="/admin?save_license"', $html);
        self::assertStringContainsString('</form>', $html);
        self::assertSame($major > 2, strpos($html, 'inline alerts') !== false);
        self::assertSame($ignored, strpos($html, 'checked="checked"') !== false);
        $expected = strpos($license, 'ignore-site-') === false ? htmlspecialchars($license, ENT_QUOTES, 'UTF-8') : '';
        self::assertStringContainsString('name="license_key" value="' . $expected . '"', $html);
        self::assertStringContainsString('Site Ignored (add-on disabled)', $html);
        self::assertStringContainsString('Save License Key', $html);
    }

    /**
     * Supply legacy and modern license form contexts.
     *
     * @return array
     */
    public static function viewContexts(): array
    {
        $root = dirname(__DIR__, 3) . '/low_yearly_archives/';
        $views = array($root . 'views/license.php');
        $contexts = array();
        foreach ($views as $view) {
            $contexts[] = array($view, 2, 'key-"<&', false);
            $contexts[] = array($view, 7, 'low-yearly-archives-ignore-site-1', true);
        }

        return $contexts;
    }
}
