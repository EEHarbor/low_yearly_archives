<?php

class OriginalScopeStoredCallback
{
    /**
     * Catch the generated event inside the original native operator scope.
     *
     * @return void
     */
    public function __wakeup(): void
    {
        try {
            \trigger_error('Low Yearly original operator-scope sentinel', \E_USER_DEPRECATED);
        } catch (\ErrorException $error) {
        }
    }

    /**
     * Exercise the actual shipped getter while retaining the diagnostic journal.
     *
     * @return void
     */
    public static function run(): void
    {
        $present = array_key_exists('low_yearly_ee', $GLOBALS);
        $previous = $present ? $GLOBALS['low_yearly_ee'] : null;
        $GLOBALS['low_yearly_ee'] = (object) array('db' => new OriginalScopeDatabase());
        try {
            $settings = (new \ReflectionClass('Low\\YearlyArchives\\FluxCapacitor\\Conduit\\GlobalFieldSettings'))->newInstanceWithoutConstructor();
            $settings->fieldtype_name = 'low_yearly_archives';
            $settings->getSettings();
        } finally {
            if ($present) { $GLOBALS['low_yearly_ee'] = $previous; } else { unset($GLOBALS['low_yearly_ee']); }
        }
    }
}

class OriginalScopeDatabase
{
    /**
     * Supply a real native serialized callback at the Active Record row boundary.
     *
     * @param string $method Requested database operation.
     * @param array $arguments Original operation arguments.
     * @return mixed Owned row or fluent database receiver.
     */
    public function __call($method, $arguments)
    {
        if ($method === 'num_rows') { return 1; }
        if ($method === 'row') { return \base64_encode(\serialize(array('keep' => new OriginalScopeStoredCallback()))); }

        return $this;
    }
}
