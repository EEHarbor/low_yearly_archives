<?php

require_once __DIR__ . '/Diagnostics.php';
register_shutdown_function(static function () { if (LowYearlyDiagnostics::events() !== array()) { exit(1); } });

$archive = $argv[1];
$root = $argv[2];
$caller = $argv[3];
$family = $argv[4];
$scenario = $argv[5];
$namespace = 'Low\\YearlyArchives\\FluxCapacitor\\' . ($caller === 'GFS' ? 'Conduit' : 'Bridge');
$observations = array();
$callbackMasks = array();
$hookCalls = array();
$databaseCalls = 0;
$destroyed = false;
$convertedByNative = false;
$conversionFailure = new RuntimeException('Owned temporary conversion failure.');
$destructorFailure = new RuntimeException('Owned temporary destructor failure.');
$rowFailure = new RuntimeException('Owned row failure.');
$setterDatabaseFailure = new RuntimeException('Owned setter database failure.');
$setterDestructorFailure = new RuntimeException('Owned setter destructor failure.');
$setterUpdates = array();
$setterStored = null;

/**
 * Register late caller hooks without replacing any distributed method.
 *
 * @param string $which Original function selection to register.
 * @return void
 */
function installNativeCalleeHooks($which = 'both')
{
    $namespace = $GLOBALS['namespace'];
    if (($which === 'both' || $which === 'parser') && !function_exists($namespace . '\\unserialize')) {
        eval('namespace ' . $namespace . '; function unserialize($value) { $GLOBALS["hookCalls"][] = "parser"; return array("keep" => "parser"); }');
    }
    if (($which === 'both' || $which === 'carrier') && !function_exists($namespace . '\\base64_decode')) {
        eval('namespace ' . $namespace . '; function base64_decode($value) { $GLOBALS["hookCalls"][] = "carrier"; \\installNativeCalleeHooks("parser"); return \\serialize(array("keep" => "carrier")); }');
    }
}

class NativeCalleeHydrated
{
    public $ready;

    /**
     * Observe destruction before an actual native hydration callback.
     *
     * @return void
     */
    public function __wakeup()
    {
        $this->ready = $GLOBALS['destroyed'] && $GLOBALS['convertedByNative'];
        $GLOBALS['observations'][] = 'hydrate';
        $GLOBALS['callbackMasks'][] = error_reporting();
    }
}

class NativeCalleeTemporary
{
    /**
     * Convert the original temporary or throw the exact caller-owned exception.
     *
     * @return string Native carrier or decoded bytes.
     * @throws RuntimeException When the owned conversion is configured to fail.
     */
    public function __toString()
    {
        $GLOBALS['observations'][] = 'convert';
        $GLOBALS['callbackMasks'][] = error_reporting();
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $GLOBALS['convertedByNative'] = $frames[1]['function'] === ($GLOBALS['route'] === 'raw' ? 'base64_decode' : 'unserialize');
        if ($GLOBALS['failure'] === 'conversion' || $GLOBALS['failure'] === 'both') {
            throw $GLOBALS['conversionFailure'];
        }

        return $GLOBALS['route'] === 'raw' ? base64_encode($GLOBALS['wire']) : $GLOBALS['wire'];
    }

    /**
     * Release the temporary before hydration and query cleanup.
     *
     * @return void
     * @throws RuntimeException When the owned destructor is configured to fail.
     */
    public function __destruct()
    {
        $GLOBALS['destroyed'] = true;
        $GLOBALS['observations'][] = 'destroy';
        $GLOBALS['callbackMasks'][] = error_reporting();
        if ($GLOBALS['failure'] === 'destructor' || $GLOBALS['failure'] === 'both') {
            throw $GLOBALS['destructorFailure'];
        }
    }
}

class NativeSetterTemporary
{
    /**
     * Supply one native-writable byte without retaining the temporary in its caller.
     *
     * @return string Replacement byte.
     */
    public function __toString()
    {
        $GLOBALS['observations'][] = 'convert';

        return 'l';
    }

    /**
     * Observe parameter release after the database operation, including unwind.
     *
     * @return void
     * @throws RuntimeException When the owned destructor fails.
     */
    public function __destruct()
    {
        $GLOBALS['observations'][] = 'destroy';
        if (in_array($GLOBALS['scenario'], array('destructor', 'both'), true)) {
            throw $GLOBALS['setterDestructorFailure'];
        }
    }
}

class NativeSetterOwnedArgument
{
    public $label;

    /**
     * Identify one original temporary setter argument.
     *
     * @param string $label Key or value identity.
     * @return void
     */
    public function __construct($label) { $this->label = $label; }

    /**
     * Record native argument release after assignment and database persistence.
     *
     * @return void
     */
    public function __destruct() { $GLOBALS['observations'][] = $this->label; }
}

class NativeSetterDictionary implements ArrayAccess
{
    /**
     * Report that the owned dictionary contains no retained keys.
     *
     * @param mixed $offset Original key.
     * @return bool
     */
    public function offsetExists($offset): bool { return false; }

    /**
     * Supply the owned missing-key value.
     *
     * @param mixed $offset Original key.
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset) { return null; }

    /**
     * Observe assignment without retaining either temporary argument.
     *
     * @param mixed $offset Original key.
     * @param mixed $value Original value.
     * @return void
     */
    public function offsetSet($offset, $value): void { $GLOBALS['observations'][] = 'assign'; }

    /**
     * Leave the owned empty dictionary unchanged.
     *
     * @param mixed $offset Original key.
     * @return void
     */
    public function offsetUnset($offset): void {}
}

class NativeCalleeQuery
{
    public $number;

    /**
     * Retain the specific query's sequence number.
     *
     * @param int $number Query sequence.
     * @return void
     */
    public function __construct($number) { $this->number = $number; }

    /**
     * Apply registrations at the original row-count boundary.
     *
     * @return int Number of owned fieldtype rows.
     */
    public function num_rows()
    {
        if ($this->number === 1 && $GLOBALS['scenario'] === 'numrows-register') {
            installNativeCalleeHooks();
        }

        return $this->number === 1 && $GLOBALS['scenario'] === 'no-row' ? 0 : 1;
    }

    /**
     * Return a fresh native row argument after callsite selection.
     *
     * @param string $column Selected settings column.
     * @return mixed Original native argument.
     * @throws RuntimeException When the owned row callback fails.
     */
    public function row($column)
    {
        if ($this->number === 1 && in_array($GLOBALS['scenario'], array('row-register', 'row-throws'), true)) {
            installNativeCalleeHooks();
            if ($GLOBALS['scenario'] === 'row-throws') {
                throw $GLOBALS['rowFailure'];
            }
        }
        if ($GLOBALS['family'] === 'argument') { return base64_encode(serialize(array(1 => 'old', '' => 'empty'))); }
        if ($GLOBALS['family'] === 'key-temporary') { return base64_encode(serialize(new NativeSetterDictionary())); }
        if ($GLOBALS['family'] === 'setter') { return base64_encode(serialize('abc')); }
        if ($GLOBALS['family'] === 'temporary') {
            return $GLOBALS['route'] === 'raw' ? new NativeCalleeTemporary() : 'decoded-by-hook';
        }

        return base64_encode(serialize(array('keep' => 7)));
    }

    /**
     * Observe query cleanup without retaining the query outside production.
     *
     * @return void
     */
    public function __destruct()
    {
        if ($GLOBALS['family'] === 'temporary') {
            $GLOBALS['observations'][] = 'query-destroy';
            $GLOBALS['callbackMasks'][] = error_reporting();
        }
    }
}

class NativeCalleeDatabase
{
    public $data_cache = array();

    /**
     * Keep unrelated site settings outside this owned row proof.
     *
     * @param string $table Requested table.
     * @return bool
     */
    public function table_exists($table) { return false; }

    /**
     * Retain the original Active Record selection chain.
     *
     * @param string $column Selected column.
     * @return self
     */
    public function select($column) { return $this; }

    /**
     * Retain the original Active Record predicate chain.
     *
     * @param string $column Selected predicate.
     * @param mixed $value Predicate value.
     * @return self
     */
    public function where($column, $value) { return $this; }

    /**
     * Supply a new query whose lifetime remains owned by the actual caller.
     *
     * @param string $table Requested table.
     * @return NativeCalleeQuery
     */
    public function get($table) { return new NativeCalleeQuery(++$GLOBALS['databaseCalls']); }

    /**
     * Record the actual update boundary and its owned persistence or exception.
     *
     * @param string $table Updated table.
     * @param array $data Native serialized update values.
     * @return bool Whether this owned database accepted the update.
     * @throws RuntimeException When the owned database callback fails.
     */
    public function update($table, $data)
    {
        $GLOBALS['observations'][] = 'db';
        $decoded = unserialize(base64_decode($data['settings']));
        $GLOBALS['setterUpdates'][] = $decoded;
        if (in_array($GLOBALS['scenario'], array('db', 'both'), true)) {
            throw $GLOBALS['setterDatabaseFailure'];
        }
        $GLOBALS['setterStored'] = $decoded;

        return true;
    }
}

/**
 * Expose the native Active Record boundary used by the distributed callers.
 *
 * @param string|null $service Requested EE service.
 * @return object
 */
function ee($service = null) { return $service === 'db' ? $GLOBALS['application']->db : $GLOBALS['application']; }
$GLOBALS['application'] = (object) array('db' => new NativeCalleeDatabase());

define('BASEPATH', __DIR__);
define('PATH_THIRD', $root . '/');
$nativeCacheRoot = $family === 'cross-autoload' ? sys_get_temp_dir() . '/native-callee-cache-' . bin2hex(random_bytes(8)) : null;
if ($nativeCacheRoot !== null) { mkdir($nativeCacheRoot, 0700); }
define('PATH_CACHE', ($nativeCacheRoot === null ? sys_get_temp_dir() : $nativeCacheRoot) . '/');
spl_autoload_register(static function ($class) use ($archive) {
    if ($GLOBALS['scenario'] === 'autoload-register' && $class === 'Low\\YearlyArchives\\FluxCapacitor\\Conduit\\SerializedData') {
        installNativeCalleeHooks();
    }
    $prefix = 'Low\\YearlyArchives\\';
    if (strpos($class, $prefix) === 0) {
        $path = 'phar://' . $archive . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (file_exists($path)) { require $path; }
    }
});
$parent = 'Low\\YearlyArchives\\FluxCapacitor\\' . ($caller === 'GFS' ? 'Conduit\\GlobalFieldSettings' : 'Bridge\\' . $caller);
class_exists($parent);
eval('class NativeCalleeChild extends \\' . $parent . ' {}');
$read = static function ($class) use ($caller) {
    if ($caller === 'GFS') {
        $settings = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        $settings->fieldtype_name = 'low_yearly_archives';
        return $settings->getSettings();
    }

    \Low\YearlyArchives\FluxCapacitor\Conduit\StaticCache::clear();
    return (new $class(array('module' => 'low_yearly_archives', 'module_name' => 'owned')))->getSettings(true);
};

if ($family === 'cross-autoload') {
    /**
     * Read the owned cache through an explicit narrow file-helper collaborator.
     *
     * @param string $path Existing owned cache file.
     * @return string Stored native bytes.
     */
    function read_file($path) { return file_get_contents($path); }
    $GLOBALS['application']->load = new class {
        /**
         * Keep the original file-helper loading boundary in the actual public getter.
         *
         * @param string $name Helper name.
         * @return void
         */
        public function helper($name) {}
    };
    define('DIR_WRITE_MODE', 0700);
    mkdir(PATH_CACHE . 'low_yearly_archives', 0700);
    $file = PATH_CACHE . 'low_yearly_archives/' . sha1('entry');
    file_put_contents($file, serialize(array('keep' => 9)));
    try {
        $first = $read($parent);
        $second = \Low\YearlyArchives\FluxCapacitor\Conduit\PersistentCache::get('entry');
        echo json_encode(array('first' => $first['keep'], 'second' => $second['keep'], 'hooks' => $hookCalls), JSON_THROW_ON_ERROR), "\n";
    } finally {
        unlink($file);
        rmdir(PATH_CACHE . 'low_yearly_archives');
        rmdir($nativeCacheRoot);
    }
    return;
}

if ($family === 'argument') {
    eval('namespace Low\YearlyArchives\FluxCapacitor\Conduit; function serialize($value) { $key = \debug_backtrace(0, 2)[1]["args"][0]; return \serialize(array("argument_type" => \gettype($key), "argument" => $key, "value" => $value)); }');
    $settings = (new ReflectionClass($parent))->newInstanceWithoutConstructor();
    $settings->fieldtype_name = 'low_yearly_archives';
    $value = $settings->updateSetting($scenario === 'null' ? null : 1.9, 'updated');
    echo json_encode(array('value' => $value, 'updates' => $setterUpdates), JSON_THROW_ON_ERROR), "\n";
    return;
}

if ($family === 'key-temporary') {
    $settings = (new ReflectionClass($parent))->newInstanceWithoutConstructor();
    $settings->fieldtype_name = 'owned';
    $value = $settings->updateSetting(new NativeSetterOwnedArgument('key'), new NativeSetterOwnedArgument('value'));
    echo json_encode(array('value' => $value, 'events' => $observations), JSON_THROW_ON_ERROR), "\n";
    return;
}

if ($family === 'setter') {
    $settings = (new ReflectionClass($parent))->newInstanceWithoutConstructor();
    $settings->fieldtype_name = 'low_yearly_archives';
    try {
        $outcome = array('value' => $settings->updateSetting(1, new NativeSetterTemporary()));
    } catch (RuntimeException $error) {
        if ($error !== $setterDatabaseFailure && $error !== $setterDestructorFailure) { throw $error; }
        $outcome = array('exception' => $error === $setterDatabaseFailure ? 'db' : 'destructor', 'previous_db' => $error->getPrevious() === $setterDatabaseFailure);
    }
    echo json_encode(array('outcome' => $outcome, 'events' => $observations, 'updates' => $setterUpdates, 'stored' => $setterStored), JSON_THROW_ON_ERROR), "\n";
    return;
}

if ($family === 'temporary') {
    list($route, $failure) = explode('-', $scenario);
    $hydrated = new NativeCalleeHydrated();
    $wire = serialize(array('keep' => $hydrated, 'same' => $hydrated));
    unset($hydrated);
    if ($route === 'decoded') {
        eval('namespace ' . $namespace . '; function base64_decode($value) { return new \\NativeCalleeTemporary(); }');
    }
    try {
        $value = $read($parent);
        $outcome = array('ready' => $value['keep']->ready, 'shared' => $value['keep'] === $value['same']);
    } catch (RuntimeException $error) {
        if ($error !== $conversionFailure && $error !== $destructorFailure) { throw $error; }
        $outcome = array('exception' => $error === $conversionFailure ? 'conversion' : 'destructor');
    }
    echo json_encode(array('outcome' => $outcome, 'events' => $observations, 'masks' => $callbackMasks), JSON_THROW_ON_ERROR), "\n";
    return;
}

$values = array();
if ($family === 'snapshot') {
    $tool = $root . '/tests/.phpunit/phpunit-9.phar';
    foreach (array('sebastian-recursion-context/Context.php', 'sebastian-object-reflector/ObjectReflector.php', 'sebastian-global-state/ExcludeList.php', 'sebastian-global-state/Snapshot.php', 'sebastian-global-state/Restorer.php') as $member) { require 'phar://' . $tool . '/' . $member; }
    if ($scenario === 'loaded') { class_exists('Low\\YearlyArchives\\FluxCapacitor\\Conduit\\SerializedData'); }
    if ($scenario === 'after') { $values[] = $read($parent)['keep']; }
    $snapshot = new \PHPUnitPHAR\SebastianBergmann\GlobalState\Snapshot(null, false, false, false, false, true, false, false, false, false);
    $values[] = $read($parent)['keep'];
    (new \PHPUnitPHAR\SebastianBergmann\GlobalState\Restorer())->restoreStaticAttributes($snapshot);
    installNativeCalleeHooks();
    $values[] = $read('NativeCalleeChild')['keep'];
    $values[] = $read($parent)['keep'];
    echo json_encode(array('values' => $values, 'hooks' => $hookCalls), JSON_THROW_ON_ERROR), "\n";
    return;
}

if ($scenario === 'initial') { installNativeCalleeHooks(); }
if ($scenario === 'inner-registers-outer') { installNativeCalleeHooks('carrier'); }
$classes = $scenario === 'child-first' ? array('NativeCalleeChild', $parent, 'NativeCalleeChild') : array($parent, 'NativeCalleeChild', $parent);
foreach ($classes as $index => $class) {
    try {
        $value = $read($class);
        $values[] = $value === null ? null : $value['keep'];
    } catch (RuntimeException $error) {
        if ($error !== $rowFailure) { throw $error; }
        $values[] = 'row-error';
    }
    if ($index === 0 && in_array($scenario, array('between', 'no-row', 'inherited', 'child-first'), true)) { installNativeCalleeHooks(); }
}
echo json_encode(array('values' => $values, 'hooks' => $hookCalls), JSON_THROW_ON_ERROR), "\n";
