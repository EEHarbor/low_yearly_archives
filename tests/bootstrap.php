<?php

error_reporting(E_ALL);
date_default_timezone_set('UTC');

define('BASEPATH', dirname(__DIR__) . '/');
define('PATH_THIRD', dirname(__DIR__) . '/');
define('APP_VER', getenv('LOW_YEARLY_EE_VERSION') ?: '7.5.17');
define('REQ', getenv('LOW_YEARLY_REQUEST') ?: 'PAGE');
define('PATH_CACHE', sys_get_temp_dir() . '/low-yearly-archives-' . getmypid() . '/');
define('DIR_WRITE_MODE', 0700);
define('FILE_WRITE_MODE', 0600);
define('SYSPATH', sys_get_temp_dir() . '/low-yearly-no-system/');
define('APPPATH', sys_get_temp_dir() . '/low-yearly-no-app/');

require __DIR__ . '/support/ExpressionEngine.php';
require __DIR__ . '/support/BranchCoverage.php';
require __DIR__ . '/support/TestCase.php';
if (getenv('LOW_YEARLY_MEASURE_ARCHIVE')) {
    spl_autoload_register(function ($class) {
        $prefix = 'Low\\YearlyArchives\\FluxCapacitor\\';
        if (strpos($class, $prefix) !== 0) {
            return;
        }
        $path = __DIR__ . '/.coverage-source/FluxCapacitor/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }, true, true);
}
require PATH_THIRD . 'low_yearly_archives/autoload.php';
