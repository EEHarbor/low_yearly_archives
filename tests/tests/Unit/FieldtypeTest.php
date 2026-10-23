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
}
