<?php

class MalformedStoredCallback
{
    /**
     * Emit the generated diagnostic after native parsing reaches malformed later bytes.
     *
     * @return void
     */
    public function __wakeup(): void
    {
        @trigger_error('Low Yearly malformed callback sentinel', E_USER_DEPRECATED);
    }

    /**
     * Decode the actual archive payload while its caller catches a callback diagnostic.
     *
     * @return void
     */
    public static function run(): void
    {
        $payload = 'a:3:{i:0;S:1:"\41";i:1;' . serialize(new self()) . 'i:2;C:1:"X":-100:{S:1:"\41";}}';
        try {
            \Low\YearlyArchives\FluxCapacitor\Conduit\SerializedData::decode($payload);
        } catch (\Throwable $exception) {
        }
    }
}
