<?php

use Low\YearlyArchives\FluxCapacitor\Base\Mcp;

final class McpTest extends LowYearlyTestCase
{
    /**
     * Redirect the module landing route and render absent/existing license data.
     *
     * @return void
     */
    public function testLicensePages(): void
    {
        $mcp = new Mcp();
        self::assertSame('4.1.1', $mcp->version);
        self::assertNull($mcp->nav);
        $this->ee->functions = new LowYearlySpy(array('redirect' => 'redirected'));
        self::assertSame('redirected', $mcp->index());
        self::assertSame('addons/settings/low_yearly_archives/license', $this->ee->functions->calls[0][1][0]->path);
        $this->ee->view = (object) array('cp_page_title' => 'License');
        $view = $this->getMockBuilder(stdClass::class)->addMethods(array('make', 'render'))->getMock();
        $view->expects(self::exactly(3))->method('make')->with('low_yearly_archives:license')->willReturnSelf();
        $data = array();
        $view->method('render')->willReturnCallback(function ($variables) use (&$data) { $data[] = $variables; return 'license view'; });
        $this->ee->services['View'] = $view;
        self::assertSame('license view', $mcp->license()['body']);
        $this->ee->db->rows = array(array('license_key' => 'key-123', 'ignore_site' => '0'));
        self::assertSame('license view', $mcp->license()['body']);
        $this->ee->db->rows[0]['ignore_site'] = '1';
        self::assertSame('license view', $mcp->license()['body']);
        self::assertSame(array('', 'key-123', ''), array_column($data, 'license_key'));
        self::assertSame(array(false, 0, 1), array_column($data, 'ignore_site'));
        self::assertSame('addons/settings/low_yearly_archives/save_license', $data[0]['action_url']->path);
    }
}
