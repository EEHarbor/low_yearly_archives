<?php

use Low\YearlyArchives\FluxCapacitor\Base\Ft;

final class FieldtypeTest extends LowYearlyTestCase
{
    /**
     * Honor fieldtype capabilities and explicit channel settings/input.
     *
     * @return void
     */
    public function testFieldtypeCapabilitiesAndChannel(): void
    {
        $fieldtype = new class extends Ft {};
        self::assertSame('4.1.1', $fieldtype->version);
        self::assertSame(array('name' => 'Low Yearly Archives', 'version' => '4.1.1'), $fieldtype->info);
        self::assertTrue($fieldtype->accepts_content_type('channel'));
        self::assertFalse($fieldtype->accepts_content_type('member'));
        self::assertTrue($fieldtype->update('4.0.0'));
        $fieldtype->settings['field_channel_id'] = '7';
        self::assertSame('7', $fieldtype->getChannelId());
        $fieldtype->settings = array();
        $this->ee->input->values['channel_id'] = '3';
        self::assertSame('3', $fieldtype->getChannelId());
    }

    /**
     * Produce settings rows and preserve legacy alias arguments.
     *
     * @return void
     */
    public function testSettingsRowsAndPackaging(): void
    {
        $fieldtype = new class extends Ft {};
        $fieldtype->displaySettingsRow('Title', '<input>', 'Description', true);
        $fieldtype->_display_settings_add_row('Other', '<select>');

        self::assertSame(array(
            'title' => 'Title', 'desc' => 'Description', 'wide' => true,
            'fields' => array(array('type' => 'html', 'content' => '<input>')),
        ), $fieldtype->rows[0]);
        self::assertSame(array('field_options_low_yearly_archives' => array(
            'label' => 'field_options', 'group' => 'low_yearly_archives', 'settings' => $fieldtype->rows,
        )), $fieldtype->displaySettingsReturn());
        self::assertSame(array('custom' => array('label' => 'custom label', 'group' => 'group', 'settings' => $fieldtype->rows)), $fieldtype->_package_display_settings('custom', 'group', 'custom label'));
    }

    /**
     * Resolve a saved entry's channel through the real Model query chain.
     *
     * @param object|null $entry
     * @param string|false $expected
     * @return void
     * @dataProvider entryChannels
     */
    public function testSavedEntryResolvesItsChannel($entry, $expected): void
    {
        $fieldtype = new class extends Ft {};
        $fieldtype->testContentId = 9;
        $query = $this->getMockBuilder(stdClass::class)->addMethods(array('get', 'filter', 'first'))->getMock();
        $query->expects(self::once())->method('get')->with('ChannelEntry')->willReturnSelf();
        $query->expects(self::once())->method('filter')->with('entry_id', 9)->willReturnSelf();
        $query->expects(self::once())->method('first')->willReturn($entry);
        $this->ee->services['Model'] = $query;

        self::assertSame($expected, $fieldtype->getChannelId());
    }

    /**
     * Include a stored entry and EE's null result for a missing or inaccessible entry.
     *
     * @return array
     */
    public static function entryChannels(): array
    {
        return array(array((object) array('channel_id' => '4'), '4'), array(null, false));
    }

    /**
     * Recover the channel from a create route while leaving unrelated requests unset.
     *
     * @param string $uri
     * @param int|false $expected
     * @return void
     * @dataProvider createRoutes
     */
    public function testCreateRouteChannelFallback($uri, $expected): void
    {
        $this->ee->uri->path = $uri;
        $fieldtype = new class extends Ft {};

        self::assertSame($expected, $fieldtype->getChannelId());
    }

    /**
     * Supply channel creation and unrelated routes with no configured entry.
     *
     * @return array
     */
    public static function createRoutes(): array
    {
        return array(array('cp/publish/create/7', 7), array('cp/publish/edit/entry/9', false));
    }

    /**
     * Use legacy channel input and table-row packaging in a native EE2 request.
     *
     * @param array $input
     * @param string|false $expected
     * @return void
     * @dataProvider legacyChannels
     */
    public function testNativeLegacyFieldtype(array $input, $expected): void
    {
        list($status, $stdout, $stderr) = LowYearlyNativeProcess::run(array(__DIR__ . '/../../support/native.php', 'legacy-fieldtype', '2.5.5', json_encode($input, JSON_THROW_ON_ERROR)));
        self::assertSame(0, $status, $stdout . $stderr);
        self::assertSame('', $stderr);
        $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($expected, $result['channel']);
        self::assertSame(array(
            array('add_row', array('Label', '<input>')),
            array('add_row', array(array('colspan' => '2', 'data' => 'Wide<select>'))),
        ), $result['rows']);
        self::assertTrue($result['settings']);
    }

    /**
     * Include numeric-string channel input and an absent legacy input.
     *
     * @return array
     */
    public static function legacyChannels(): array
    {
        return array(array(array('channel_id' => '3'), '3'), array(array(), false));
    }
}
