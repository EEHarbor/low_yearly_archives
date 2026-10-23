<?php

error_reporting(E_ALL);
date_default_timezone_set('UTC');
$root = dirname(__DIR__, 2);
$scenario = $argv[1];
set_error_handler(function ($severity, $message, $file, $line) use ($root) {
    $source = strpos($file, $root . '/low_yearly_archives/') !== false;
    $deprecation = $severity === E_DEPRECATED || $severity === E_USER_DEPRECATED;
    if ($source || $deprecation || (error_reporting() & $severity)) {
        throw new ErrorException(($source ? '' : 'Test support diagnostic: ') . $message, 0, $severity, $file, $line);
    }

    return false;
});

if ($scenario === 'guard') {
    include $root . '/low_yearly_archives/pi.low_yearly_archives.php';
    throw new RuntimeException('The direct-access guard did not exit.');
}

define('PATH_THIRD', $scenario === 'missing-archive' ? sys_get_temp_dir() . '/missing-low-yearly/' : $root . '/');
define('BASEPATH', $root . '/');
define('APP_VER', isset($argv[2]) ? $argv[2] : '7.5.17');
define('REQ', in_array($scenario, array('save-license', 'mcp-constructor', 'cp-bases'), true) ? 'CP' : 'PAGE');
define('PATH_CACHE', sys_get_temp_dir() . '/cache/');
define('DIR_WRITE_MODE', 0700);
define('FILE_WRITE_MODE', 0600);
define('APPPATH', sys_get_temp_dir() . '/legacy-app/');
define('SYSPATH', sys_get_temp_dir() . '/legacy-system/');
require __DIR__ . '/ExpressionEngine.php';
require __DIR__ . '/BranchCoverage.php';
if (getenv('LOW_YEARLY_RAW_COVERAGE')) {
    register_shutdown_function(array(LowYearlyBranchCoverage::class, 'stop'));
    LowYearlyBranchCoverage::start();
}

if ($scenario === 'fallback-loader') {
    stream_wrapper_unregister('phar');
}
require $root . '/low_yearly_archives/autoload.php';

if ($scenario === 'cp-bases') {
    $GLOBALS['low_yearly_ee'] = (object) array(
        'db' => new LowYearlyDatabase(), 'settings_db' => new LowYearlyDatabase(),
        'config' => new LowYearlyConfig(), 'services' => array(), 'service_calls' => array(),
    );
    ee()->db->tableExists = true;
    ee()->db->queryRows = array(array('Column_name' => 'license_key', 'Non_unique' => '0'));
    ee()->cp = new class {
        public $footer_item = array();

        /**
         * Return EE's shared footer storage for duplicate-pixel detection.
         *
         * @return array
         */
        public function get_foot() { return $this->footer_item; }

        /**
         * Append a pixel or script through the real CP footer boundary.
         *
         * @param string $html
         * @return void
         */
        public function add_to_foot($html) { $this->footer_item[] = $html; }
    };
    $menu = new LowYearlySpy(array('addItem' => null));
    $extension = new Low\YearlyArchives\FluxCapacitor\Base\Ext();
    $extension->cpCustomMenu($menu);
    $extension->cp_custom_menu($menu);
    $tab = new Low\YearlyArchives\FluxCapacitor\Base\Tab();
    $tabFooter = ee()->cp->footer_item;
    $fieldtype = new class extends Low\YearlyArchives\FluxCapacitor\Base\Ft {};
    echo json_encode(array(
        'menu' => $menu->calls, 'tab_version' => $tab->version,
        'fieldtype_version' => $fieldtype->version, 'fieldtype_info' => $fieldtype->info,
        'tab_footer' => $tabFooter, 'footer' => ee()->cp->footer_item,
    ), JSON_THROW_ON_ERROR);
    exit;
}

if ($scenario === 'legacy-formatting') {
    $GLOBALS['low_yearly_helper_calls'] = array();
    $GLOBALS['low_yearly_ee'] = (object) array(
        'functions' => new LowYearlySpy(array('remove_double_slashes' => 'legacy slashes')),
        'load' => new LowYearlySpy(array('helper' => null)),
    );
    $bridge = new Low\YearlyArchives\FluxCapacitor\Bridge\EE2(array('module' => 'low_yearly_archives', 'module_name' => 'Low Yearly Archives'));
    echo json_encode(array(
        'slug' => $bridge->url_title('  EXAMPLE Title '),
        'slashes' => $bridge->reduce_double_slashes('https://example.test//path'),
        'helpers' => $GLOBALS['low_yearly_helper_calls'],
        'functions' => ee()->functions->calls,
        'loader' => ee()->load->calls,
    ), JSON_THROW_ON_ERROR);
    exit;
}

if ($scenario === 'legacy-fieldtype') {
    $input = json_decode($argv[3], true, 512, JSON_THROW_ON_ERROR);
    $GLOBALS['low_yearly_ee'] = (object) array(
        'db' => new LowYearlyDatabase(), 'config' => new LowYearlyConfig(),
        'input' => new LowYearlyInput(), 'table' => new LowYearlySpy(array('add_row' => null)),
    );
    ee()->input->values = $input;
    $fieldtype = new class extends Low\YearlyArchives\FluxCapacitor\Base\Ft {};
    $fieldtype->settings['field_channel_id'] = 9;
    $fieldtype->displaySettingsRow('Label', '<input>', 'Description');
    $fieldtype->_display_settings_add_row('Wide', '<select>', '', true);
    echo json_encode(array('channel' => $fieldtype->getChannelId(), 'rows' => ee()->table->calls, 'settings' => $fieldtype->displaySettingsReturn()), JSON_THROW_ON_ERROR);
    exit;
}

if ($scenario === 'save-license' || $scenario === 'mcp-constructor') {
    $input = json_decode($argv[3], true, 512, JSON_THROW_ON_ERROR);
    $GLOBALS['low_yearly_ee'] = (object) array(
        'db' => new LowYearlyDatabase(), 'settings_db' => new LowYearlyDatabase(),
        'config' => new LowYearlyConfig(), 'alert' => new LowYearlyAlert(),
        'input' => new LowYearlyInput(), 'services' => array(), 'service_calls' => array(),
    );
    ee()->config->values['site_id'] = $input['site_id'];
    ee()->input->values = $input['post'];
    ee()->db->tableExists = true;
    ee()->db->queryRows = array(array('Column_name' => 'license_key', 'Non_unique' => '0'));
    ee()->db->count = $input['count'];
    ee()->db->tableRows = array('eeharbor_licenses' => $input['licenses'], 'sites' => $input['sites']);
    ee()->db->resultQueues['eeharbor_licenses'] = array($input['check_key_rows'] ?? $input['licenses']);
    ee()->functions = new class {
        /**
         * Observe the redirect boundary and terminate the request as EE does.
         *
         * @param LowYearlyUrl $url
         * @return void
         */
        public function redirect($url)
        {
            echo json_encode(array('redirect' => $url->path, 'database' => ee()->db->calls, 'alerts' => ee()->alert->calls), JSON_THROW_ON_ERROR);
            exit;
        }
    };
    $mcp = new Low\YearlyArchives\FluxCapacitor\Base\Mcp();
    if ($scenario === 'mcp-constructor') {
        echo json_encode(array('version' => $mcp->version, 'nav' => $mcp->nav), JSON_THROW_ON_ERROR);
        exit;
    }
    $mcp->save_license();
    throw new RuntimeException('The license action did not redirect.');
}

if ($scenario === 'legacy-url' || $scenario === 'legacy-routes') {
    $input = json_decode($argv[3], true, 512, JSON_THROW_ON_ERROR);
    define('BASE', $input['base']);
    define('AMP', '&amp;');
    $GLOBALS['low_yearly_ee'] = (object) array(
        'config' => new LowYearlyConfig(), 'session' => new LowYearlySession(),
        'cp' => new LowYearlySpy(array('set_right_nav' => null)),
    );
    ee()->config->values = array_merge(ee()->config->values, $input['config']);
    $bridge = new Low\YearlyArchives\FluxCapacitor\Bridge\EE2(array('module' => 'low_yearly_archives', 'module_name' => 'Low Yearly Archives'));
    if ($scenario === 'legacy-url') {
        echo json_encode(array('base' => $bridge->getBaseURL('/'), 'route' => $bridge->getBaseURL('settings', AMP . 'extra=1')));
        exit;
    }
    $url = $input['module']
        ? $bridge->moduleURL($input['mode'], $input['variables'])
        : $bridge->cpURL($input['path'], $input['mode'], $input['variables']);
    $bridge->getNav(array('settings' => 'Settings', 'https://example.test/docs' => 'Docs'));
    echo json_encode(array('url' => $url, 'nav' => ee()->cp->calls));
    exit;
}

if ($scenario === 'unrelated-loader') {
    $loaders = spl_autoload_functions();
    $loader = end($loaders);
    if ($loader('Another\\Namespace\\Missing') !== null) {
        throw new RuntimeException('Foreign namespace was not ignored.');
    }
    echo 'unrelated namespace ignored';
    exit;
}

if ($scenario === 'missing-archive' || $scenario === 'missing-class') {
    try {
        class_exists('Low\\YearlyArchives\\FluxCapacitor\\Missing');
    } catch (Exception $error) {
        echo $error->getMessage();
        exit;
    }
    throw new RuntimeException('Missing archive/class did not throw.');
}

if (!class_exists('Low\\YearlyArchives\\FluxCapacitor\\Conduit\\StaticCache')) {
    throw new RuntimeException('Native archived class was not loaded.');
}
$class = 'Low\\YearlyArchives\\FluxCapacitor\\FluxCapacitor';
$flux = new $class();
echo get_parent_class('Low\\YearlyArchives\\FluxCapacitor\\Bridge\\EE') . ':' . $flux->getEEVersion(false);
