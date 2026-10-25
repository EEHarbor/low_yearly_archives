<?php

namespace Low\YearlyArchives\FluxCapacitor\Conduit {
    /**
     * Observe the untouched carrier at its original consumer namespace.
     *
     * @param mixed $value Original stored carrier.
     * @return mixed Consumer-selected decoded value.
     */
    function base64_decode($value)
    {
        $GLOBALS['caller_hooks'][] = array('Conduit-base64', $value);
        return $GLOBALS['hook_decoded'];
    }

}

namespace Low\YearlyArchives\FluxCapacitor\Bridge {
    /**
     * Observe the untouched carrier at its original consumer namespace.
     *
     * @param mixed $value Original stored carrier.
     * @return mixed Consumer-selected decoded value.
     */
    function base64_decode($value)
    {
        $GLOBALS['caller_hooks'][] = array('Bridge-base64', $value);
        return $GLOBALS['hook_decoded'];
    }

}

namespace {
    class NativeDecodedValue
    {
        /**
         * Convert a native parser input once at its original callback boundary.
         *
         * @return string
         */
        public function __toString()
        {
            $GLOBALS['decoded_conversions']++;
            if ($GLOBALS['decoded_shape'] === 'legacy-string') {
                return 'a:1:{S:7:"decoded";S:8:"accepted";}';
            }
            $serialized = \serialize(array('decoded' => 'accepted'));
            return $GLOBALS['decoded_shape'] === 'accepted-prefix' ? $serialized . 'unused' : $serialized;
        }
    }
    class NativeHookDatabase {
        public $data_cache=array();
        /**
         * Keep site settings outside this fieldtype hook proof.
         *
         * @param string $table
         * @return bool
         */
        public function table_exists($table){return false;}
        /**
         * Retain the native selected column.
         *
         * @param string $value
         * @return self
         */
        public function select($value){return $this;}
        /**
         * Retain the fieldtype predicate.
         *
         * @param string $key
         * @param mixed $value
         * @return self
         */
        public function where($key,$value){return $this;}
        /**
         * Supply the owned row boundary.
         *
         * @param string $table
         * @return self
         */
        public function get($table){return $this;}
        /**
         * Supply one stored fieldtype row.
         *
         * @return int
         */
        public function num_rows(){return 1;}
        /**
         * Return the exact native carrier without normalization.
         *
         * @param string $column
         * @return mixed
         */
        public function row($column){return $GLOBALS['hook_carrier'];}
    }
    /**
     * Expose only the native Active Record boundary.
     *
     * @param string|null $service
     * @return object
     */
    function ee($service=null){static $app;if(!$app){$app=(object)array('db'=>new NativeHookDatabase());}return $service==='db'?$app->db:$app;}
    $GLOBALS['caller_hooks']=array();
    $GLOBALS['hook_carrier']=$argv[4]==='null-carrier'?null:($argv[4]==='empty-carrier'?'':'carrier');
    $GLOBALS['decoded_conversions'] = 0;
    $GLOBALS['decoded_shape'] = $argv[4];
    $GLOBALS['hook_decoded'] = new NativeDecodedValue();
    require __DIR__ . '/Diagnostics.php';
    register_shutdown_function(static function () { if (LowYearlyDiagnostics::events() !== array()) { exit(1); } });
    define('BASEPATH',__DIR__);define('PATH_THIRD',$argv[2].'/');define('PATH_CACHE',sys_get_temp_dir().'/');
    $base='phar://'.$argv[1].'/FluxCapacitor/';
    foreach(array('Conduit/StaticCache.php','Conduit/SerializedData.php','Conduit/GlobalFieldSettings.php','Bridge/AbstractBridge.php') as $file){if(file_exists($base.$file)){require $base.$file;}}
    if($argv[3]==='GFS'){$settings=(new \ReflectionClass('Low\\YearlyArchives\\FluxCapacitor\\Conduit\\GlobalFieldSettings'))->newInstanceWithoutConstructor();$settings->fieldtype_name='low_yearly_archives';$value=$settings->getSettings();}
    else {require $base.'Bridge/'.$argv[3].'.php';$class='Low\\YearlyArchives\\FluxCapacitor\\Bridge\\'.$argv[3];$settings=new $class(array('module'=>'low_yearly_archives','module_name'=>'owned'));$value=$settings->getSettings(true);}
    echo json_encode(array('hooks' => $GLOBALS['caller_hooks'], 'value' => $value['decoded'], 'conversions' => $GLOBALS['decoded_conversions']), JSON_THROW_ON_ERROR);
}
