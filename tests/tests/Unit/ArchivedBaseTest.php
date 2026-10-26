<?php

use Low\YearlyArchives\FluxCapacitor\Base\Ext;
use Low\YearlyArchives\FluxCapacitor\Base\Mod;
use Low\YearlyArchives\FluxCapacitor\Base\Pi;
use Low\YearlyArchives\FluxCapacitor\Base\Tab;
use Low\YearlyArchives\FluxCapacitor\Base\Upd;
use Low\YearlyArchives\FluxCapacitor\FluxCapacitor;

final class ArchivedBaseTest extends LowYearlyTestCase
{
    /**
     * Verify base constructors consume the actual add-on configuration.
     *
     * @return void
     */
    public function testBaseConstructorsUseShippedMetadata(): void
    {
        foreach (array(new Ext(), new Mod(), new Pi(), new Tab()) as $base) {
            self::assertInstanceOf(FluxCapacitor::class, $base->flux);
            self::assertSame('4.1.1', $base->version);
        }
        $extension = new Ext();
        self::assertSame('Low Yearly Archives', $extension->name);
        self::assertSame('Monthly archive listings grouped by year.', $extension->description);
        self::assertSame('http://eeharbor.com/low_yearly_archives/documentation', $extension->docs_url);
    }

    /**
     * Verify template parameter trimming, fallbacks and untrimmed values.
     *
     * @param mixed $value
     * @param bool $trim
     * @param mixed $expected
     * @return void
     * @dataProvider moduleParameters
     */
    public function testModuleParameter($value, $trim, $expected): void
    {
        $this->ee->TMPL->tagparams['value'] = $value;

        self::assertSame($expected, $this->invoke(new Mod(), 'getParameter', array('value', 'fallback', $trim)));
    }

    /**
     * Cover empty, false, zero, whitespace, entity and ordinary parameters.
     *
     * @return array
     */
    public static function moduleParameters(): array
    {
        return array(
            array(false, true, 'fallback'), array('', true, 'fallback'), array('0', true, 'fallback'),
            array(' &nbsp;&#32; ', true, 'fallback'), array(' &nbsp; value &#32; ', true, 'value'),
            array('  value  ', false, '  value  '), array('value', true, 'value'),
        );
    }

    /**
     * Preserve extension registration data and avoid inserting duplicate hooks.
     *
     * @return void
     */
    public function testExtensionRegistrationAndAliases(): void
    {
        $extension = new Ext();
        $extension->settings = array('enabled' => false, 'limit' => 0);
        self::assertTrue($extension->registerExtension('publish', 'entry_submit', 3, 'n'));
        self::assertSame(array('insert', array('extensions', array(
            'class' => Ext::class, 'method' => 'publish', 'hook' => 'entry_submit',
            'settings' => serialize($extension->settings), 'priority' => 3, 'version' => '4.1.1', 'enabled' => 'n',
        ))), end($this->ee->db->calls));

        $extension->settings = null;
        self::assertTrue($extension->registerExtension('default_hook'));
        self::assertSame(array(), $extension->settings);
        $this->ee->db->rows = array(array('extension_id' => '1'));
        $before = count($this->ee->db->calls);
        self::assertTrue($extension->registerExtension('default_hook'));
        self::assertCount($before + 1, $this->ee->db->calls);
        self::assertTrue($this->invoke($extension, 'unregisterExtension', array('publish', 'entry_submit')));
        self::assertTrue($this->invoke($extension, 'unregister_extension', array('default_hook')));
        self::assertSame(array('delete', array('extensions', array('class' => Ext::class, 'method' => 'default_hook', 'hook' => 'default_hook'))), end($this->ee->db->calls));
    }

    /**
     * Register configured hooks and update/disable the matching extension only.
     *
     * @return void
     */
    public function testExtensionLifecycleAndLegacyAliases(): void
    {
        $extension = new Ext();
        $extension->hooks = array('entry_submit');
        self::assertTrue($extension->activateExtension());
        self::assertTrue($extension->activate_extension());
        self::assertTrue($extension->updateExtension('4.0.0'));
        self::assertTrue($extension->update_extension('4.0.0'));
        self::assertTrue($this->invoke($extension, 'update_version'));
        self::assertTrue($extension->disableExtension());
        self::assertTrue($extension->disable_extension());
        self::assertContains(array('where', array('class', Ext::class)), $this->ee->db->calls);
        self::assertContains(array('delete', array('extensions')), $this->ee->db->calls);
        self::assertContains(array('delete', array('menu_items')), $this->ee->db->calls);
        self::assertContains(array('update', array('extensions', array('version' => '4.1.1'), array('class' => Ext::class))), $this->ee->db->calls);
        self::assertTrue($extension->cpCustomMenu(new stdClass()));
        self::assertTrue($extension->cp_custom_menu(new stdClass()));
    }

    /**
     * Preserve the tab compatibility aliases and all entry variable sources.
     *
     * @return void
     */
    public function testTabBehavior(): void
    {
        $tab = new Tab();
        self::assertSame(array(), $tab->display(2, 3));
        self::assertSame(array(), $tab->publish_tabs(2));
        self::assertTrue($tab->save((object) array('entry_id' => 3), array()));
        self::assertTrue($tab->publish_data_db(array()));
        self::assertSame(array('entry_id' => 3, 'channel_id' => 4, 'site_id' => 5), $tab->getEntryVariables((object) array('entry_id' => 3, 'channel_id' => 4, 'site_id' => 5), array()));
        self::assertSame(array('entry_id' => '3', 'channel_id' => '4', 'site_id' => '5'), $tab->getEntryVariables(null, array('entry_id' => '3', 'meta' => array('channel_id' => '4', 'site_id' => '5'))));
        self::assertSame(array('entry_id' => 0, 'channel_id' => 0, 'site_id' => 0), $tab->getEntryVariables(null, array()));
    }

    /**
     * Insert modules with the configured flags and avoid extension-only modules.
     *
     * @return void
     */
    public function testUpdaterInstallAndUninstall(): void
    {
        $updater = (new ReflectionClass(Upd::class))->newInstanceWithoutConstructor();
        $updater->flux = new FluxCapacitor();
        $updater->version = '4.1.1';
        $updater->module_name = 'Low_yearly_archives';
        $this->ee->functions = new class {
            public $cleared = array();
            /**
             * Record the database cache invalidation.
             *
             * @param string $type
             * @return void
             */
            public function clear_caching($type) { $this->cleared[] = $type; }
        };
        self::assertTrue($updater->install());
        self::assertSame(array('db'), $this->ee->functions->cleared);
        self::assertSame(array('insert', array('modules', array(
            'module_name' => 'Low_yearly_archives', 'module_version' => '4.1.1', 'has_cp_backend' => 'y', 'has_publish_fields' => 'n',
        ))), end($this->ee->db->calls));
        $this->ee->db->calls = array();
        $updater->ext_settings = 'y';
        self::assertTrue($updater->install());
        self::assertSame(array(), $this->ee->db->calls);
        self::assertFalse($updater->update());
        self::assertFalse($updater->update('4.1.1'));
        $this->ee->db->rows = array(array('module_id' => '7'));
        self::assertTrue($updater->uninstall());
        foreach (array('module_member_roles', 'modules', 'actions', 'extensions', 'menu_items') as $table) {
            self::assertContains(array('delete', array($table)), $this->ee->db->calls);
        }
    }

    /**
     * Keep a missing optional extension include visible without blocking uninstall tests.
     *
     * @return void
     */
    public function testUpdaterUpgradeDoesNotEmitSourceDiagnostics(): void
    {
        $updater = (new ReflectionClass(Upd::class))->newInstanceWithoutConstructor();
        $updater->flux = new FluxCapacitor();
        $updater->version = '4.1.1';
        $updater->module_name = 'Low_yearly_archives';

        self::assertTrue($updater->update('4.0.0'));
    }
}
