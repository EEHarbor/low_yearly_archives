<?php

require 'phar://' . dirname(__DIR__, 2) . '/low_yearly_archives/low_yearly_archives.phar/FluxCapacitor/Base/Mcp.php';

class LowYearlyNativePublicMcp extends \Low\YearlyArchives\FluxCapacitor\Base\Mcp { public $data = array('seed' => true); }
class LowYearlyNativeProtectedMcp extends \Low\YearlyArchives\FluxCapacitor\Base\Mcp { protected $data = array('seed' => true); }
class LowYearlyNativePrivateMcp extends \Low\YearlyArchives\FluxCapacitor\Base\Mcp { private $data = array('seed' => true); }

class LowYearlyNativeMagicMcp extends \Low\YearlyArchives\FluxCapacitor\Base\Mcp
{
    private $store = array('data' => array('seed' => true));
    public $calls = array();

    /**
     * Return legacy extension state by reference for native offset writes.
     *
     * @param string $name Extension property name.
     * @return mixed
     */
    public function &__get($name)
    {
        $this->calls[] = 'get:' . $name;
        return $this->store[$name];
    }

    /**
     * Retain the extension's existing native property assignment hook.
     *
     * @param string $name Extension property name.
     * @param mixed $value Assigned value.
     * @return void
     */
    public function __set($name, $value): void
    {
        $this->calls[] = 'set:' . $name;
        $this->store[$name] = $value;
    }
}

/**
 * Supply the input boundary used by the actual archived pagination method.
 *
 * @return object
 */
function ee() { return $GLOBALS['geo_native_ee']; }

$GLOBALS['geo_native_ee'] = (object) array('input' => new class {
    /**
     * Select the second pagination page.
     *
     * @param string $name Query parameter name.
     * @return int
     */
    public function get($name) { return 2; }
});
$kind = substr($mode, strlen('mcp-api-'));
$classes = array('bare' => \Low\YearlyArchives\FluxCapacitor\Base\Mcp::class,
    'public' => LowYearlyNativePublicMcp::class, 'protected' => LowYearlyNativeProtectedMcp::class,
    'private' => LowYearlyNativePrivateMcp::class, 'magic' => LowYearlyNativeMagicMcp::class,
    'array_access' => LowYearlyNativeProtectedMcp::class);
$object = (new ReflectionClass($classes[$kind]))->newInstanceWithoutConstructor();
if ($kind === 'array_access') {
    $property = new ReflectionProperty($object, 'data');
    if (PHP_VERSION_ID < 80100) { $property->setAccessible(true); }
    $property->setValue($object, new ArrayObject(array('seed' => true)));
}
$object->flux = new class {
    /**
     * Preserve the optional inferred route boundary.
     *
     * @param string $method Caller method.
     * @return string
     */
    public function moduleURL($method) { return 'module/' . $method; }

    /**
     * Return a visible pagination result without booting EE rendering.
     *
     * @param array $options Pagination options.
     * @return string
     */
    public function pagination($options) { return 'links'; }
};
$model = new class {
    public $calls = array();

    /**
     * Return a native countable collection from the cloned query.
     *
     * @return ArrayObject
     */
    public function all() { return new ArrayObject(array(1, 2, 3)); }

    /**
     * Retain the original query's requested result limit.
     *
     * @param int $limit Result limit.
     * @return self
     */
    public function limit($limit) { $this->calls[] = array('limit', $limit); return $this; }

    /**
     * Retain the original query's requested result offset.
     *
     * @param int $offset Result offset.
     * @return self
     */
    public function offset($offset) { $this->calls[] = array('offset', $offset); return $this; }
};
$error = null;
try {
    $object->paginateModel($model, 'explicit', 10);
} catch (Throwable $caught) {
    $error = array(get_class($caught), $caught->getMessage());
}
$property = new ReflectionProperty($object, $kind === 'magic' ? 'store' : 'data');
if (PHP_VERSION_ID < 80100) { $property->setAccessible(true); }
$data = $kind === 'bare' ? $object->data : $property->getValue($object);
if ($kind === 'magic') { $data = $data['data']; }
echo json_encode(array('error' => $error, 'data_type' => is_object($data) ? get_class($data) : gettype($data),
    'data' => $data, 'calls' => $model->calls, 'magic' => $kind === 'magic' ? $object->calls : array()), JSON_THROW_ON_ERROR);
