<?php

namespace Low\YearlyArchives\FluxCapacitor\Conduit;

class SerializedData
{
    /**
     * Decode stored PHP values with historical escaped strings and prefix tolerance.
     *
     * @param mixed $value Stored serialized bytes.
     * @param bool $quietRange Whether this caller historically suppressed native integer-range warnings.
     * @return mixed Decoded value, or false when the payload is invalid.
     * @throws \Throwable When hydrated object code or another diagnostic fails.
     */
    public static function decode($value, $quietRange = true)
    {
        if (!is_string($value) || $value === '') {
            return false;
        }

        if (strpos($value, 'S:') !== false) {
            $value = self::normalizeEscapedStrings($value);
        }

        $invalid = false;
        $parserLine = 0;
        $previous = null;
        $previous = set_error_handler(static function ($severity, $message, $file, $line) use (&$invalid, &$parserLine, &$previous, $quietRange) {
            // Native parse failures return false; callback diagnostics keep their original handler.
            if (($severity === E_NOTICE || $severity === E_WARNING) && $file === __FILE__ && $line === $parserLine
                && preg_match('/^(?:unserialize\(\): (?:Error at offset [0-9]+ of [0-9]+ bytes|Unexpected end of serialized data|Maximum depth of [0-9]+ exceeded.*)|Insufficient data for unserializing - -?[0-9]+ required, [0-9]+ present)$/D', $message)) {
                $invalid = true;

                return true;
            }

            if (($severity === E_NOTICE || $severity === E_WARNING) && $file === __FILE__ && $line === $parserLine
                && preg_match('/^unserialize\(\): (?:Extra data starting at offset [0-9]+ of [0-9]+ bytes|Numerical result out of range)$/D', $message)
                && ($quietRange || $message !== 'unserialize(): Numerical result out of range')) {
                // Preserve native prefixes and only historically quiet integer-range results.
                return true;
            }

            if (is_callable($previous)) {
                return $previous(...func_get_args());
            }

            return false;
        });

        try {
            $parserLine = __LINE__ + 1;
            $decoded = unserialize($value);

            return $invalid ? false : $decoded;
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Rewrite escaped tokens while leaving unrecognized remainders to the native parser.
     *
     * @param string $value
     * @return string Normalized prefix and untouched unrecognized remainder.
     */
    private static function normalizeEscapedStrings($value)
    {
        $length = strlen($value);
        $position = 0;
        $result = '';
        $depth = 0;

        while ($position < $length) {
            $start = $position;
            $type = $value[$position];

            if ($type === '}') {
                $result .= '}';
                $position++;
                $depth--;
                if ($depth <= 0) {
                    return $result . substr($value, $position);
                }
                continue;
            }

            if (preg_match('/\G(?:N;|[bidrR]:[^;]*;|a:[0-9]+:\{)/', $value, $match, 0, $position)) {
                $result .= $match[0];
                $position += strlen($match[0]);
                if (substr($match[0], -1) === '{') {
                    $depth++;
                }
                if ($depth === 0) {
                    return $result . substr($value, $position);
                }
                continue;
            }

            if (!preg_match('/\G([sSEOC]):([0-9]+):"/', $value, $match, 0, $position)) {
                return $result . substr($value, $start);
            }

            $position += strlen($match[0]);
            $size = self::boundedLength($match[2], $length - $position);
            if ($size === null) {
                return $result . substr($value, $start);
            }
            $contents = '';

            if ($type === 'S') {
                for ($i = 0; $i < $size; $i++) {
                    if ($position >= $length) {
                        return $result . substr($value, $start);
                    }
                    $byte = $value[$position++];
                    if ($byte === '\\') {
                        $hex = substr($value, $position, 2);
                        if (strlen($hex) !== 2 || strspn($hex, '0123456789abcdefABCDEF') !== 2) {
                            return $result . substr($value, $start);
                        }
                        $byte = chr(hexdec($hex));
                        $position += 2;
                    }
                    $contents .= $byte;
                }
            } else {
                if ($size > $length - $position) {
                    return $result . substr($value, $start);
                }
                $position += $size;
            }

            if ($type === 'O' || $type === 'C') {
                if (!preg_match('/\G":([+-]?[0-9]+):\{/', $value, $tail, 0, $position)) {
                    return $result . substr($value, $start);
                }
                $position += strlen($tail[0]);
                if ($type === 'C') {
                    $payload_length = self::boundedLength($tail[1], $length - $position - 1);
                    if ($payload_length === null || $value[$position + $payload_length] !== '}') {
                        return $result . substr($value, $start);
                    }
                    $position += $payload_length + 1;
                }
                $result .= substr($value, $start, $position - $start);
                if ($type === 'O') {
                    $depth++;
                }
                if ($depth === 0) {
                    return $result . substr($value, $position);
                }
                continue;
            }

            if (substr($value, $position, 2) !== '";') {
                return $result . substr($value, $start);
            }
            $position += 2;
            $result .= $type === 'S' ? 's:' . strlen($contents) . ':"' . $contents . '";' : substr($value, $start, $position - $start);
            if ($depth === 0) {
                return $result . substr($value, $position);
            }
        }

        return $result;
    }

    /**
     * Convert a nonnegative wire length only when it fits the remaining input.
     *
     * @param string $value Decimal length with an optional sign.
     * @param int $maximum Available input bytes.
     * @return int|null Bounded length, or null for an invalid length.
     */
    private static function boundedLength($value, $maximum)
    {
        if ($maximum < 0) {
            return null;
        }

        if ($value[0] === '-') {
            if (trim(substr($value, 1), '0') !== '') {
                return null;
            }
            return 0;
        }

        $digits = ltrim($value, '+0');
        $bound = (string) $maximum;
        if (strlen($digits) > strlen($bound) || (strlen($digits) === strlen($bound) && strcmp($digits, $bound) > 0)) {
            return null;
        }

        return (int) $digits;
    }
}
