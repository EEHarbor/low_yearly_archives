<?php

declare(strict_types=1);

use Low\YearlyArchives\FluxCapacitor\Conduit\SerializedData;

class SerializedDataTest extends LowYearlyTestCase
{
    /**
     * Preserve historical escaped strings and native complete-value prefix reads.
     *
     * @dataProvider legacyPayloads
     * @param string $payload Stored historical bytes.
     * @param mixed $expected Original native PHP value.
     * @return void
     */
    public function testLegacyPayloadsRetainValues($payload, $expected): void
    {
        self::assertSame(serialize($expected), serialize(SerializedData::decode($payload)));
    }

    /**
     * Keep escaped tokens distinct from literal bytes in ordinary serialized strings.
     *
     * @return array
     */
    public static function legacyPayloads(): array
    {
        return array(
            array('S:1:"\\41";', 'A'),
            array('a:1:{s:4:"name";S:1:"\\41";}', array('name' => 'A')),
            array('S:3:"\\00\\ffA";', "\0\xffA"),
            array('a:2:{s:6:"S:fake";s:5:"S:x;y";s:4:"name";S:1:"\\41";}', array('S:fake' => 'S:x;y', 'name' => 'A')),
            array('O:8:"stdClass":1:{S:4:"\\6eame";S:1:"\\41";}', (object) array('name' => 'A')),
            array('a:1:{s:4:"name";S:1:"\\41";}unused', array('name' => 'A')),
            array('a:1:{s:4:"name";s:1:"A";}unused', array('name' => 'A')),
            array(serialize('text S: is literal'), 'text S: is literal'),
            array('i:99999999999999999999999999999;', PHP_INT_MAX),
            array('i:-99999999999999999999999999999;', PHP_INT_MIN),
            array('a:2:{i:0;S:1:"\\41";i:1;i:99999999999999999999999999999;}', array('A', PHP_INT_MAX)),
            array('a:2:{i:0;S:1:"\\41";i:1;i:-99999999999999999999999999999;}', array('A', PHP_INT_MIN)),
            array('S:000000000000000000000000000001:"\\41";', 'A'),
            array('a:2:{i:0;s:000000000000000000000000000001:"A";i:1;S:1:"\\42";}', array('A', 'B')),
            array('O:000000000000000000000000000008:"stdClass":1:{s:1:"x";S:1:"\\41";}', (object) array('x' => 'A')),
            array('O:8:"stdClass":+1:{s:1:"x";S:1:"\\41";}', (object) array('x' => 'A')),
            array('O:8:"stdClass":+01:{s:1:"x";S:1:"\\41";}', (object) array('x' => 'A')),
            array('a:2:{i:0;O:8:"stdClass":-0:{}i:1;S:1:"\\41";}', array(new \stdClass(), 'A')),
        );
    }

    /**
     * Supply malformed tails after an already hydrated callback and normalized value.
     *
     * @return array
     */
    public static function malformedTails(): array
    {
        return array(
            array('S:2:"\41";}'),
            array('S:1:"\4g";}'),
            array('s:20:"S:short";}'),
            array('X:S:1:"\41";}'),
            array('C:1:"X":-100:{S:1:"\41";}}'),
            array('S:999999999999999999999999999999:"x";}'),
            array('s:999999999999999999999999999999:"S:x";}'),
            array('O:999999999999999999999999999999:"S:X":0:{}}'),
            array('C:999999999999999999999999999999:"S:X":0:{}}'),
            array('E:999999999999999999999999999999:"S:X";}'),
            array('C:1:"X":+999999999999999999999999999999:{S:}}'),
            array('C:1:"X":-999999999999999999999999999999:{S:}}'),
        );
    }

    /**
     * Preserve native cleanup callbacks when a later token makes the payload invalid.
     *
     * @dataProvider malformedTails
     * @param string $tail Malformed later token.
     * @return void
     */
    public function testMalformedTailRetainsQueuedCallback($tail): void
    {
        SerializedQueuedCallback::$calls = array();
        $wire = 'a:3:{i:0;S:1:"\41";i:1;' . serialize(new SerializedQueuedCallback('ordinary')) . 'i:2;' . $tail;
        self::assertFalse(SerializedData::decode($wire));
        self::assertSame(array('ordinary'), SerializedQueuedCallback::$calls);
    }

    /**
     * Preserve native callback exceptions and restore the handler after malformed data.
     *
     * @dataProvider malformedTails
     * @param string $tail Malformed later token.
     * @return void
     */
    public function testMalformedTailRetainsCallbackException($tail): void
    {
        SerializedQueuedCallback::$calls = array();
        $wire = 'a:3:{i:0;S:1:"\41";i:1;' . serialize(new SerializedQueuedCallback('exception')) . 'i:2;' . $tail;
        $handler = static function () { throw new \RuntimeException('Unexpected diagnostic'); };
        set_error_handler($handler);
        try {
            try {
                SerializedData::decode($wire);
                self::fail('The queued callback exception must propagate.');
            } catch (\RuntimeException $exception) {
                self::assertSame('Queued callback exception', $exception->getMessage());
            }
            self::assertSame(array('exception'), SerializedQueuedCallback::$calls);
            $restored = set_error_handler($handler);
            restore_error_handler();
            self::assertSame($handler, $restored);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Forward callback diagnostics with the native PHP 7.4 context when available.
     *
     * @dataProvider malformedTails
     * @param string $tail Malformed later token.
     * @return void
     */
    public function testMalformedTailForwardsCallbackDiagnosticAndContext($tail): void
    {
        $events = array();
        $handler = static function ($severity, $message, $file, $line, $context = null) use (&$events) {
            $events[] = array($severity, $message, $file, is_array($context) && ($context['native_context_marker'] ?? null) === 'retained');
            throw new \RuntimeException('Queued callback diagnostic forwarded');
        };
        set_error_handler($handler);
        try {
            $wire = 'a:3:{i:0;S:1:"\41";i:1;' . serialize(new SerializedQueuedCallback('diagnostic')) . 'i:2;' . $tail;
            try {
                SerializedData::decode($wire);
                self::fail('The queued diagnostic must propagate.');
            } catch (\RuntimeException $exception) {
                self::assertSame('Queued callback diagnostic forwarded', $exception->getMessage());
            }
            self::assertSame(array(array(E_USER_WARNING, 'Queued callback diagnostic', __FILE__, PHP_VERSION_ID < 80000)), $events);
            $restored = set_error_handler($handler);
            restore_error_handler();
            self::assertSame($handler, $restored);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Preserve native reference numbering after escaped-string normalization.
     *
     * @return void
     */
    public function testLegacyEscapedStringReferencesRemainShared(): void
    {
        $decoded = SerializedData::decode('a:2:{i:0;S:1:"\\41";i:1;R:2;}');
        $decoded[0] = 'changed';
        self::assertSame('changed', $decoded[1]);
    }

    /**
     * Preserve scalar, nested, object and shared-reference serialization values.
     *
     * @return void
     */
    public function testValidValuesPreserveNativeTypesAndReferences(): void
    {
        $object = (object) array('fraction' => 1.5, 'nullable' => null);
        $value = array('first' => $object, 'second' => $object, 'zero' => '0', 'empty' => array());
        $payload = serialize($value);
        $decoded = SerializedData::decode($payload);
        self::assertSame($payload, serialize($decoded));
        self::assertSame($decoded['first'], $decoded['second']);

        foreach (array(false, null, 0, '0', '', 1.5, array()) as $scalar) {
            self::assertSame($scalar, SerializedData::decode(serialize($scalar)));
        }
    }

    /**
     * Preserve mutable references shared by serialized array elements.
     *
     * @return void
     */
    public function testScalarReferencesRemainShared(): void
    {
        $shared = 'original';
        $value = array('first' => &$shared, 'second' => &$shared);
        $decoded = SerializedData::decode(serialize($value));
        $decoded['first'] = 'changed';
        self::assertSame('changed', $decoded['second']);
    }

    /**
     * Keep malformed bytes on the existing false-return parse-error contract.
     *
     * @dataProvider invalidPayloads
     * @param mixed $payload Invalid stored payload.
     * @return void
     */
    public function testMalformedValuesReturnFalse($payload): void
    {
        self::assertFalse(SerializedData::decode($payload));
    }

    /**
     * Cover truncation, invalid string lengths, bad references and missing data.
     *
     * @return array
     */
    public static function invalidPayloads(): array
    {
        return array(array(null), array(false), array(array()), array(''), array('broken'),
            array('a:1:{s:1:"x";}'), array('s:9:"short";'), array('R:999;'),
            array('S:1:"\\4g";'), array('S:2:"\\41";'), array('S:1:"\\41"'));
    }

    /**
     * Leave custom serialized payload bytes to their original native callback.
     *
     * @dataProvider customPayloadCounts
     * @param string $prefix Native payload-length sign and leading zeroes.
     * @return void
     */
    public function testCustomPayloadWithLiteralEscapedTokenIsNotRewritten($prefix): void
    {
        $class = SerializedCustomPayload::class;
        $contents = 'literal S:1:"\\41"; stays unchanged';
        $wire = 'C:' . strlen($class) . ':"' . $class . '":' . $prefix . strlen($contents) . ':{' . $contents . '}';
        $decoded = SerializedData::decode($wire);
        self::assertInstanceOf($class, $decoded);
        self::assertSame($contents, $decoded->payload);
    }

    /**
     * Preserve native signed zero custom lengths after an escaped value.
     *
     * @return void
     */
    public function testSignedZeroCustomPayloadLengthsRemainValid(): void
    {
        $class = SerializedCustomPayload::class;
        foreach (array('0', '+0', '-0', '-000000000000000000000000000') as $length) {
            $wire = 'a:2:{i:0;S:1:"\41";i:1;C:' . strlen($class) . ':"' . $class . '":' . $length . ':{}}';
            $decoded = SerializedData::decode($wire);
            self::assertSame('A', $decoded[0]);
            self::assertSame('', $decoded[1]->payload);
        }
    }

    /**
     * Supply native accepted lexical forms for custom payload lengths.
     *
     * @return array
     */
    public static function customPayloadCounts(): array
    {
        return array(array(''), array('+'), array('+0'), array('000000000000000000000000000'), array('+000000000000000000000000000'));
    }

    /**
     * Forward parser-looking native warnings originating inside hydrated callbacks.
     *
     * @return void
     */
    public function testNestedNativeParserDiagnosticIsNotMistakenForStorageFailure(): void
    {
        $events = array();
        $failure = new \RuntimeException('Nested native parser diagnostic propagated');
        $handler = static function ($severity, $message, $file, $line) use (&$events, $failure) {
            $events[] = array($severity, $message, $file, $line);
            throw $failure;
        };
        set_error_handler($handler);
        try {
            try {
                SerializedData::decode(serialize(new SerializedNativeDiagnosticCallback()));
                self::fail('The callback parser diagnostic must propagate.');
            } catch (\RuntimeException $exception) {
                self::assertSame($failure, $exception);
            }
            self::assertCount(1, $events);
            self::assertContains($events[0][0], array(E_NOTICE, E_WARNING));
            self::assertStringContainsString('unserialize(): Error at offset', $events[0][1]);
            self::assertSame(__FILE__, $events[0][2]);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Forward hydrated callbacks to the previous handler and restore it on throw.
     *
     * @return void
     */
    public function testUnexpectedCallbackDiagnosticIsForwardedAndHandlerRestored(): void
    {
        $events = array();
        $failure = new \RuntimeException('Callback diagnostic propagated');
        $handler = static function ($severity, $message, $file, $line) use (&$events, $failure) {
            $events[] = array($severity, $message, $file, $line);
            throw $failure;
        };
        set_error_handler($handler);

        try {
            try {
                SerializedData::decode(serialize(new SerializedDiagnosticCallback()));
                self::fail('A callback diagnostic must reach the previous handler.');
            } catch (\RuntimeException $exception) {
                self::assertSame($failure, $exception);
            }

            self::assertCount(1, $events);
            self::assertSame(E_USER_WARNING, $events[0][0]);
            self::assertSame('Low Yearly serialization callback sentinel', $events[0][1]);
            self::assertSame(__FILE__, $events[0][2]);
            $restored = set_error_handler($handler);
            restore_error_handler();
            self::assertSame($handler, $restored);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Restore the prior handler after the native parser rejects malformed input.
     *
     * @return void
     */
    public function testMalformedDecodeRestoresPreviousHandler(): void
    {
        $handler = static function () { throw new \RuntimeException('Unexpected diagnostic'); };
        set_error_handler($handler);

        try {
            self::assertFalse(SerializedData::decode('broken'));
            $restored = set_error_handler($handler);
            restore_error_handler();
            self::assertSame($handler, $restored);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Restore the previous diagnostic handler after an ordinary successful parse.
     *
     * @return void
     */
    public function testSuccessfulDecodeRestoresPreviousHandler(): void
    {
        $handler = static function () {
            throw new \RuntimeException('Unexpected diagnostic');
        };
        set_error_handler($handler);

        try {
            self::assertSame(array('id' => 42), SerializedData::decode(serialize(array('id' => 42))));
            $restored = set_error_handler($handler);
            restore_error_handler();
            self::assertSame($handler, $restored);
        } finally {
            restore_error_handler();
        }
    }
}

class SerializedDiagnosticCallback
{
    /**
     * Emit an intentional callback diagnostic for the forwarding control.
     *
     * @return void
     */
    public function __wakeup(): void
    {
        trigger_error('Low Yearly serialization callback sentinel', E_USER_WARNING);
    }
}

class SerializedNativeDiagnosticCallback
{
    /**
     * Emit a native parser diagnostic outside the storage decoder's own callsite.
     *
     * @return void
     */
    public function __wakeup(): void
    {
        unserialize('broken');
    }
}

class SerializedCustomPayload implements \Serializable
{
    public $payload;

    /**
     * Return the legacy custom serialization bytes.
     *
     * @return string
     */
    public function serialize(): string { return $this->payload; }

    /**
     * Retain exactly the custom bytes supplied by the native parser.
     *
     * @param string $data Custom serialized bytes.
     * @return void
     */
    public function unserialize($data): void { $this->payload = $data; }

    /**
     * Support the modern serialization protocol without legacy interface deprecations.
     *
     * @return array
     */
    public function __serialize(): array { return array('payload' => $this->payload); }

    /**
     * Restore values supplied by the modern native serialization protocol.
     *
     * @param array $data Serialized property values.
     * @return void
     */
    public function __unserialize(array $data): void { $this->payload = $data['payload']; }
}

class SerializedQueuedCallback
{
    public static $calls = array();
    public $behavior;

    /**
     * Select the callback side effect for native cleanup parity.
     *
     * @param string $behavior Callback behavior.
     * @return void
     */
    public function __construct($behavior)
    {
        $this->behavior = $behavior;
    }

    /**
     * Observe native wakeup ordering, exceptions and diagnostic context.
     *
     * @return void
     * @throws \RuntimeException When the callback requests an exception.
     */
    public function __wakeup(): void
    {
        self::$calls[] = $this->behavior;
        $native_context_marker = 'retained';
        if ($this->behavior === 'exception') {
            throw new \RuntimeException('Queued callback exception');
        }
        if ($this->behavior === 'diagnostic') {
            trigger_error('Queued callback diagnostic', E_USER_WARNING);
        }
    }
}
