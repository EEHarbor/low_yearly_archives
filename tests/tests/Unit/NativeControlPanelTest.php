<?php

final class NativeControlPanelTest extends LowYearlyTestCase
{
    /**
     * Execute CP extension aliases and tab/fieldtype constructors with shared pixel state.
     *
     * @return void
     */
    public function testNativeBaseControlPanelBehavior(): void
    {
        list($status, $stdout, $stderr) = LowYearlyNativeProcess::run(array(__DIR__ . '/../../support/native.php', 'cp-bases', '7.5.17'));
        self::assertSame(0, $status, $stdout . $stderr);
        self::assertSame('', $stderr);
        $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        $expectedMenu = array('addItem', array('Low Yearly Archives', array('path' => 'addons/settings/low_yearly_archives/index', 'variables' => array())));
        self::assertSame(array($expectedMenu, $expectedMenu), $result['menu']);
        self::assertSame('4.1.1', $result['tab_version']);
        self::assertSame('4.1.1', $result['fieldtype_version']);
        self::assertSame(array('name' => 'Low Yearly Archives', 'version' => '4.1.1'), $result['fieldtype_info']);
        self::assertCount(2, $result['tab_footer']);
        self::assertSame($result['tab_footer'], $result['footer']);
        self::assertStringContainsString('<div id="low_yearly_archives__up"', $result['footer'][0]);
        self::assertSame(1, preg_match('/\?([a-f0-9]{1024})/', $result['footer'][0]));
        self::assertStringContainsString('<script type="text/javascript">', $result['footer'][1]);
    }
}
