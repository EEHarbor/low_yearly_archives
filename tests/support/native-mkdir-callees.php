<?php

require_once __DIR__ . '/Diagnostics.php';
register_shutdown_function(static function () {
    if (LowYearlyDiagnostics::events() !== array()) { exit(1); }
});

class NativeMkdirStream
{
    public $context;
    public static $root;
    public static $calls = array();

    /**
     * Map this owned stream to its disposable physical tree.
     *
     * @param string $path Stream path.
     * @return string Physical owned path.
     */
    public static function target($path)
    {
        return self::$root . '/' . substr($path, strlen('nativemkdir://'));
    }

    /**
     * Report native permissions without emitting missing-file diagnostics.
     *
     * @param string $path Stream path.
     * @param int $flags Native lookup flags.
     * @return array|false Existing native file metadata.
     */
    public function url_stat($path, $flags)
    {
        $target = self::target($path);

        return file_exists($target) ? stat($target) : false;
    }

    /**
     * Register the module hook during the first original native mkdir callback.
     *
     * @param string $path Stream path.
     * @param int $mode Directory mode.
     * @param int $options Native stream options.
     * @return bool Native directory creation result.
     */
    public function mkdir($path, $mode, $options)
    {
        self::$calls[] = $path;
        if (count(self::$calls) === 1) {
            eval('namespace Low\YearlyArchives\FluxCapacitor\Conduit; const DIR_WRITE_MODE = 0777; function mkdir($path, $mode) { $GLOBALS["nativeMkdirModes"][] = $mode; $GLOBALS["nativeMkdirHooks"][] = $path; return false; }');
        }

        return mkdir(self::target($path), $mode);
    }

    /**
     * Apply the original cache chmod to the owned physical path.
     *
     * @param string $path Stream path.
     * @param int $option Native metadata operation.
     * @param mixed $value Requested permissions.
     * @return bool Whether native permissions were changed.
     */
    public function stream_metadata($path, $option, $value)
    {
        return $option === STREAM_META_ACCESS ? chmod(self::target($path), $value) : false;
    }
}

/**
 * Record cache writes using a narrow file-helper collaborator.
 *
 * @param string $path Stream path.
 * @param string $data Serialized cache data.
 * @return bool Whether the owned physical write succeeded.
 */
function write_file($path, $data)
{
    $GLOBALS['nativeMkdirWrites'][] = $data;
    $GLOBALS['nativeMkdirWritePaths'][] = $path;

    return file_put_contents(NativeMkdirStream::target($path), $data) !== false;
}

class NativeMkdirLoader
{
    /**
     * Retain the existing file-helper loading boundary.
     *
     * @param string $name Helper name.
     * @return void
     */
    public function helper($name) {}

    /**
     * Retain the existing logger loading boundary.
     *
     * @param string $name Library name.
     * @return void
     */
    public function library($name) {}
}

class NativeMkdirLogger
{
    /**
     * Record the original failed module-directory outcome.
     *
     * @param string $message Developer message.
     * @return void
     */
    public function developer($message) { $GLOBALS['nativeMkdirLogs'][] = $message; }
}

/**
 * Supply the owned cache collaborators without replacing distributed methods.
 *
 * @return object Loader and logger collaborators.
 */
function ee() { return $GLOBALS['nativeMkdirEE']; }

$root = sys_get_temp_dir() . '/native-mkdir-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
mkdir($root . '/root', 0700);
NativeMkdirStream::$root = $root;
$GLOBALS['nativeMkdirHooks'] = array();
$GLOBALS['nativeMkdirModes'] = array();
$GLOBALS['nativeMkdirWrites'] = array();
$GLOBALS['nativeMkdirWritePaths'] = array();
$GLOBALS['nativeMkdirLogs'] = array();
$GLOBALS['nativeMkdirEE'] = (object) array('load' => new NativeMkdirLoader(), 'logger' => new NativeMkdirLogger());
stream_wrapper_register('nativemkdir', NativeMkdirStream::class);
define('PATH_CACHE', 'nativemkdir://root/cache/');
define('DIR_WRITE_MODE', 0700);
define('FILE_WRITE_MODE', 0600);
if (($argv[2] ?? '') === 'reference') {
    mkdir($root . '/root/cache', 0700);
    eval('namespace Low\YearlyArchives\FluxCapacitor\Conduit; function mkdir(&$path, $mode) { $GLOBALS["nativeMkdirHooks"][] = $path; $GLOBALS["nativeMkdirModes"][] = $mode; \mkdir(\NativeMkdirStream::target($path), $mode); $path = "nativemkdir://root/cache/relocated/"; return \mkdir(\NativeMkdirStream::target($path), $mode); }');
}
try {
    require 'phar://' . $argv[1] . '/FluxCapacitor/Conduit/StaticCache.php';
    require 'phar://' . $argv[1] . '/FluxCapacitor/Conduit/PersistentCache.php';
    $value = Low\YearlyArchives\FluxCapacitor\Conduit\PersistentCache::set('entry', 'stored');
    echo json_encode(array('value' => $value, 'native' => NativeMkdirStream::$calls,
        'hooks' => $GLOBALS['nativeMkdirHooks'], 'write_paths' => $GLOBALS['nativeMkdirWritePaths'], 'writes' => $GLOBALS['nativeMkdirWrites'],
        'modes' => $GLOBALS['nativeMkdirModes'], 'logged' => count($GLOBALS['nativeMkdirLogs']), 'module_exists' => is_dir($root . '/root/cache/low_yearly_archives'))), "\n";
} finally {
    stream_wrapper_unregister('nativemkdir');
    $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($entries as $entry) {
        if ($entry->isDir() && !$entry->isLink()) { rmdir($entry->getPathname()); continue; }
        unlink($entry->getPathname());
    }
    rmdir($root);
}
