<?php

namespace Low\YearlyArchives\FluxCapacitor\Conduit {
    /**
     * Model an unrelated legacy namespace function that native lexer code must ignore.
     *
     * @param string $value Input bytes.
     * @return int
     */
    function strlen($value) { $GLOBALS['parser_lexical_calls']++; return 0; }

    /**
     * Observe the original public carrier hook without changing its native bytes.
     *
     * @param string $value Base64 carrier.
     * @return string
     */
    function base64_decode($value) { $GLOBALS['parser_carrier_calls']++; return \base64_decode($value); }

    /**
     * Observe the original public parser hook without changing hydration.
     *
     * @param string $value Serialized bytes.
     * @return mixed
     */
    function unserialize($value) { $GLOBALS['parser_native_calls']++; return \unserialize($value); }
}

namespace {
    require __DIR__ . '/Diagnostics.php';
    register_shutdown_function(static function () {
        if (LowYearlyDiagnostics::events() !== array()) { exit(1); }
    });

    class NativeParserDatabase
    {
        /**
         * Retain the selected database column.
         *
         * @param string $column
         * @return self
         */
        public function select($column) { return $this; }
        /**
         * Retain the fieldtype predicate.
         *
         * @param string $column
         * @param mixed $value
         * @return self
         */
        public function where($column, $value) { return $this; }
        /**
         * Supply the owned row boundary.
         *
         * @param string $table
         * @return self
         */
        public function get($table) { return $this; }
        /**
         * Supply one actual stored value.
         *
         * @return int
         */
        public function num_rows() { return 1; }
        /**
         * Supply valid native legacy serialization.
         *
         * @param string $column
         * @return string
         */
        public function row($column) { return base64_encode('S:3:"abc";'); }
    }
    /**
     * Expose only the Active Record database boundary used by the actual member.
     *
     * @return object
     */
    function ee() { static $app; if (!$app) { $app = (object) array('db' => new NativeParserDatabase()); } return $app; }

    $GLOBALS['parser_lexical_calls'] = $GLOBALS['parser_carrier_calls'] = $GLOBALS['parser_native_calls'] = 0;
    require 'phar://' . dirname(__DIR__, 2) . '/low_yearly_archives/low_yearly_archives.phar/FluxCapacitor/Conduit/SerializedData.php';
    require 'phar://' . dirname(__DIR__, 2) . '/low_yearly_archives/low_yearly_archives.phar/FluxCapacitor/Conduit/GlobalFieldSettings.php';
    $settings = (new ReflectionClass('Low\\YearlyArchives\\FluxCapacitor\\Conduit\\GlobalFieldSettings'))->newInstanceWithoutConstructor();
    $settings->fieldtype_name = 'native';
    echo json_encode(array('value' => $settings->getSettings(), 'lexer_calls' => $GLOBALS['parser_lexical_calls'],
        'carrier_calls' => $GLOBALS['parser_carrier_calls'], 'parser_calls' => $GLOBALS['parser_native_calls']), JSON_THROW_ON_ERROR);
}
