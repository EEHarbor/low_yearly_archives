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
define('REQ', 'PAGE');
define('PATH_CACHE', sys_get_temp_dir() . '/cache/');
define('DIR_WRITE_MODE', 0700);
define('FILE_WRITE_MODE', 0600);
require __DIR__ . '/ExpressionEngine.php';

if ($scenario === 'fallback-loader') {
    stream_wrapper_unregister('phar');
}
require $root . '/low_yearly_archives/autoload.php';

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
