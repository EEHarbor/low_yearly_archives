<?php

final class LowYearlyFoundationManifest
{
    /**
     * Read stored layout flags without PharFileInfo's read-only permission view.
     *
     * @param string $path Archive filename.
     * @param int $stubLength Exact executable stub length.
     * @return array Alias, archive metadata length and per-member raw flags.
     * @throws \RuntimeException When the stored manifest is incomplete or invalid.
     */
    public static function read($path, $stubLength): array
    {
        $bytes = file_get_contents($path);
        $offset = $stubLength;
        $length = self::number($bytes, $offset);
        $end = $offset + $length;
        $count = self::number($bytes, $offset);
        self::take($bytes, $offset, 6);
        $aliasLength = self::number($bytes, $offset);
        $alias = self::take($bytes, $offset, $aliasLength);
        $metadataLength = self::number($bytes, $offset);
        self::take($bytes, $offset, $metadataLength);
        $members = array();

        for ($index = 0; $index < $count; $index++) {
            $nameLength = self::number($bytes, $offset);
            $name = self::take($bytes, $offset, $nameLength);
            self::take($bytes, $offset, 16);
            $flags = self::number($bytes, $offset);
            $memberMetadataLength = self::number($bytes, $offset);
            self::take($bytes, $offset, $memberMetadataLength);
            $members[$name] = array('permissions' => $flags & 0777, 'compression' => $flags & 0xF000, 'metadata_bytes' => $memberMetadataLength);
        }

        if ($offset !== $end || count($members) !== $count) {
            throw new \RuntimeException('Invalid Foundation manifest layout.');
        }

        return array('alias' => $alias, 'metadata_bytes' => $metadataLength, 'members' => $members);
    }

    /**
     * Read one little-endian unsigned manifest field.
     *
     * @param string $bytes Complete archive bytes.
     * @param int &$offset Current byte offset, advanced after reading.
     * @return int Unsigned field value.
     * @throws \RuntimeException When four bytes are unavailable.
     */
    private static function number($bytes, &$offset): int
    {
        return unpack('Vvalue', self::take($bytes, $offset, 4))['value'];
    }

    /**
     * Read a bounded manifest slice and advance the current offset.
     *
     * @param string $bytes Complete archive bytes.
     * @param int &$offset Current byte offset.
     * @param int $length Requested byte count.
     * @return string Requested bytes.
     * @throws \RuntimeException When the requested slice exceeds the archive.
     */
    private static function take($bytes, &$offset, $length): string
    {
        if ($offset < 0 || $length < 0 || $length > strlen($bytes) - $offset) {
            throw new \RuntimeException('Truncated Foundation manifest.');
        }

        $value = substr($bytes, $offset, $length);
        $offset += $length;

        return $value;
    }
}
