<?php

/**
 * Return the isolated EE facade or its requested service.
 *
 * @param string|null $service
 * @return mixed
 */
function ee($service = null)
{
    if ($service === null) {
        return $GLOBALS['low_yearly_ee'];
    }
    $GLOBALS['low_yearly_ee']->service_calls[] = func_get_args();

    if ($service === 'db') {
        return $GLOBALS['low_yearly_ee']->settings_db;
    }

    if ($service === 'CP/Alert') {
        return $GLOBALS['low_yearly_ee']->alert;
    }

    if ($service === 'CP/URL') {
        $arguments = func_get_args();

        return new LowYearlyUrl(isset($arguments[1]) ? $arguments[1] : '');
    }

    if (isset($GLOBALS['low_yearly_ee']->services[$service])) {
        return $GLOBALS['low_yearly_ee']->services[$service];
    }

    throw new LogicException('Unexpected EE service: ' . $service);
}

/**
 * Render a form opening tag with the values provided by the license view.
 *
 * @param string $action
 * @param array $attributes
 * @return string
 */
function form_open($action, array $attributes)
{
    return '<form action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '" class="' . $attributes['class'] . '">';
}

/**
 * Render the license field using EE's escaping contract.
 *
 * @param string $name
 * @param string $value
 * @return string
 */
function form_input($name, $value)
{
    return '<input name="' . $name . '" value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Render an ignore-site checkbox.
 *
 * @param string $name
 * @param string $value
 * @param bool|int $checked
 * @return string
 */
function form_checkbox($name, $value, $checked)
{
    return '<input type="checkbox" name="' . $name . '" value="' . $value . '"' . ($checked ? ' checked="checked"' : '') . '>';
}

/**
 * Close the license form.
 *
 * @return string
 */
function form_close()
{
    return '</form>';
}

final class LowYearlyTemplate
{
    public $tagparams = array();
    public $tagdata = '{year}{months}{month}:{num_entries}{/months}{/year}';
    public $site_ids = array(1, 3);
    public $parsed = array();
    public $noResultsCalls = 0;

    /**
     * Match Template::fetch_param's missing/null and yes/no normalization.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function fetch_param($key, $default = false)
    {
        if (!isset($this->tagparams[$key])) {
            return $default;
        }

        $value = $this->tagparams[$key];
        if (in_array($value, array('y', 'on'), true)) {
            return 'yes';
        }
        if (in_array($value, array('n', 'off'), true)) {
            return 'no';
        }

        return $value;
    }

    /**
     * Record the parser boundary without emulating EE's template parser.
     *
     * @param string $tagdata
     * @param array $variables
     * @return string
     */
    public function parse_variables($tagdata, array $variables)
    {
        $this->parsed[] = array($tagdata, $variables);

        return 'rendered archive';
    }

    /**
     * Record the no-results branch.
     *
     * @return string
     */
    public function no_results()
    {
        ++$this->noResultsCalls;

        return 'no archive entries';
    }
}

final class LowYearlyResult
{
    public $rows;
    public $num_rows;

    /**
     * Store rows with EE's result object and num_rows property contracts.
     *
     * @param array $rows
     * @return void
     */
    public function __construct(array $rows = array())
    {
        $this->rows = $rows;
        $this->num_rows = count($rows);
    }

    /**
     * Return result rows as associative arrays.
     *
     * @return array
     */
    public function result_array()
    {
        return $this->rows;
    }

    /**
     * Return result rows as objects.
     *
     * @return array
     */
    public function result()
    {
        return array_map(function ($row) { return (object) $row; }, $this->rows);
    }

    /**
     * Return the first row or one column, matching EE's null on no row.
     *
     * @param string|null $column
     * @return mixed
     */
    public function row($column = null)
    {
        if (!$this->rows) {
            return null;
        }

        return $column === null ? (object) $this->rows[0] : $this->rows[0][$column];
    }

    /**
     * Return the first row as an array.
     *
     * @return array
     */
    public function row_array()
    {
        return $this->rows ? $this->rows[0] : array();
    }

    /**
     * Return the number of result rows.
     *
     * @return int
     */
    public function num_rows()
    {
        return count($this->rows);
    }
}

final class LowYearlyDatabase
{
    public $calls = array();
    public $rows = array();
    public $queryRows = array();
    public $data_cache = array();
    public $tableExists = false;
    public $fieldExists = true;
    public $count = 0;
    public $tableRows = array();

    /**
     * Record the Active Record calls without executing customer SQL.
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call($method, array $arguments)
    {
        if (!in_array($method, array('select', 'from', 'where_in', 'group_by', 'order_by', 'join', 'where_not_in', 'where', 'update', 'insert', 'delete', 'get', 'get_where', 'query', 'table_exists', 'field_exists', 'count_all_results'), true)) {
            throw new LogicException('Unexpected database method: ' . $method);
        }
        $this->calls[] = array($method, $arguments);
        if ($method === 'get' || $method === 'get_where') {
            if (isset($arguments[0], $this->tableRows[$arguments[0]])) {
                return new LowYearlyResult($this->tableRows[$arguments[0]]);
            }

            return new LowYearlyResult($this->rows);
        }
        if ($method === 'query') {
            return new LowYearlyResult($this->queryRows);
        }
        if ($method === 'table_exists') {
            return $this->tableExists;
        }
        if ($method === 'field_exists') {
            return $this->fieldExists;
        }
        if ($method === 'count_all_results') {
            return $this->count;
        }

        return $this;
    }
}

final class LowYearlyConfig
{
    public $values = array('site_id' => 1, 'base_url' => 'https://example.test', 'site_license_key' => '');

    /**
     * Return an EE configuration value, or false when missing.
     *
     * @param string $key
     * @return mixed
     */
    public function item($key)
    {
        return isset($this->values[$key]) ? $this->values[$key] : false;
    }

    /**
     * Return a configuration path with a trailing slash.
     *
     * @param string $key
     * @return string
     */
    public function slash_item($key)
    {
        return rtrim($this->item($key), '/') . '/';
    }
}

final class LowYearlyLocalize
{
    public $now = 1705320000;

    /**
     * Return EE's short/long language keys for a calendar month.
     *
     * @param string $month
     * @return array|null
     */
    public function localize_month($month)
    {
        $keys = array(
            '01' => array('Jan', 'January'), '02' => array('Feb', 'February'),
            '03' => array('Mar', 'March'), '04' => array('Apr', 'April'),
            '05' => array('May', 'May_l'), '06' => array('Jun', 'June'),
            '07' => array('Jul', 'July'), '08' => array('Aug', 'August'),
            '09' => array('Sep', 'September'), '10' => array('Oct', 'October'),
            '11' => array('Nov', 'November'), '12' => array('Dec', 'December'),
        );

        return isset($keys[$month]) ? $keys[$month] : null;
    }
}

final class LowYearlyLanguage
{
    /**
     * Return a predictable translated language key.
     *
     * @param string $key
     * @return string
     */
    public function line($key)
    {
        return 'translated:' . $key;
    }
}

final class LowYearlySession
{
    public $cache = array();
    public $flash = array();
    /**
     * Return the fixture's logged-in member ID.
     *
     * @param string $key
     * @return int
     */
    public function userdata($key)
    {
        if ($key !== 'member_id') {
            throw new LogicException('Unexpected userdata: ' . $key);
        }

        return 42;
    }

    /**
     * Store a session cache value under the add-on namespace.
     *
     * @param string $namespace
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function set_cache($namespace, $key, $value)
    {
        $this->cache[$namespace][$key] = $value;

        return $value;
    }

    /**
     * Read a session cache value.
     *
     * @param string $namespace
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function cache($namespace, $key, $default = false)
    {
        return isset($this->cache[$namespace][$key]) ? $this->cache[$namespace][$key] : $default;
    }

    /**
     * Store a flash message for the next request.
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    public function set_flashdata($key, $value)
    {
        $this->flash[$key] = $value;
    }
}

final class LowYearlyAlert
{
    public $calls = array();
    /**
     * Return an inline alert marker for the modern license view.
     *
     * @return string
     */
    public function getAllInlines()
    {
        return '<aside>inline alerts</aside>';
    }

    /**
     * Record only the fluent alert operations the source is allowed to call.
     *
     * @param string $method
     * @param array $arguments
     * @return self
     */
    public function __call($method, array $arguments)
    {
        if (!in_array($method, array('make', 'makeInline', 'asIssue', 'asWarning', 'asSuccess', 'withTitle', 'addToBody', 'canClose', 'defer'), true)) {
            throw new LogicException('Unexpected alert method: ' . $method);
        }
        $this->calls[] = array($method, $arguments);

        return $this;
    }

    /**
     * Record the optional alert behavior checked with method_exists.
     *
     * @return self
     */
    public function cannotClose()
    {
        $this->calls[] = array('cannotClose', array());

        return $this;
    }
}

final class LowYearlyUrl
{
    public $path;
    public $variables;

    /**
     * Store the path and query parameters passed to CP/URL.
     *
     * @param string $path
     * @param array $variables
     * @return void
     */
    public function __construct($path = '', array $variables = array())
    {
        $this->path = $path;
        $this->variables = $variables;
    }

    /**
     * Create a new CP URL with independent parameters.
     *
     * @param string $path
     * @param array $variables
     * @return self
     */
    public function make($path, array $variables = array())
    {
        return new self($path, $variables);
    }

    /**
     * Compile the fixture URL for comparisons and breadcrumb array keys.
     *
     * @return string
     */
    public function compile()
    {
        return '/admin/' . $this->path . ($this->variables ? '?' . http_build_query($this->variables) : '');
    }

    /**
     * Match CP/URL's string conversion contract.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->compile();
    }
}

final class LowYearlySpy
{
    public $calls = array();
    private $responses;

    /**
     * Define supported collaborator methods and their exact responses.
     *
     * @param array $responses
     * @return void
     */
    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    /**
     * Record an allowed collaborator method or fail a misspelled API call.
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call($method, array $arguments)
    {
        if (!array_key_exists($method, $this->responses)) {
            throw new LogicException('Unexpected collaborator method: ' . $method);
        }
        $this->calls[] = array($method, $arguments);

        return $this->responses[$method];
    }
}

final class LowYearlyInput
{
    public $values = array();

    /**
     * Return an input value using EE's false-on-missing contract.
     *
     * @param string $key
     * @param bool $clean
     * @return mixed
     */
    public function get($key, $clean = false)
    {
        return isset($this->values[$key]) ? $this->values[$key] : false;
    }

    /**
     * Return a POST input value.
     *
     * @param string $key
     * @param bool $clean
     * @return mixed
     */
    public function post($key, $clean = false)
    {
        return $this->get($key, $clean);
    }

    /**
     * Return a channel input value for fieldtype APIs.
     *
     * @param string $key
     * @return mixed
     */
    public function get_post($key)
    {
        return $this->get($key);
    }
}

final class LowYearlyUri
{
    public $path = 'cp/addons/settings/low_yearly_archives/index';

    /**
     * Return the current control-panel URI string.
     *
     * @return string
     */
    public function uri_string()
    {
        return $this->path;
    }

    /**
     * Return URI segments using the one-based EE segment convention.
     *
     * @return array
     */
    public function segment_array()
    {
        return array_combine(range(1, count(explode('/', $this->path))), explode('/', $this->path));
    }
}

abstract class EE_Fieldtype
{
    public $settings = array();
    public $testContentId;

    /**
     * Expose the content ID used by the real EE_Fieldtype contract.
     *
     * @return int|null
     */
    public function content_id()
    {
        return $this->testContentId;
    }
}

/**
 * Read a cache file using EE's file helper false-on-missing contract.
 *
 * @param string $path
 * @return string|false
 */
function read_file($path)
{
    return is_file($path) ? file_get_contents($path) : false;
}

/**
 * Write cache data using EE's file helper contract.
 *
 * @param string $path
 * @param string $data
 * @return bool
 */
function write_file($path, $data)
{
    $handle = @fopen($path, 'wb');
    if ($handle === false) {
        return false;
    }
    flock($handle, LOCK_EX);
    fwrite($handle, $data);
    flock($handle, LOCK_UN);
    fclose($handle);

    return true;
}
