<?php

use Low\YearlyArchives\FluxCapacitor\Conduit\StaticCache;

final class StaticCacheTest extends LowYearlyTestCase
{
    /**
     * Verify scoped cache storage, key normalization, raw keys and clearing.
     *
     * @return void
     */
    public function testCacheStorageLifecycle(): void
    {
        self::assertFalse(StaticCache::get('missing'));
        self::assertFalse(StaticCache::has('missing'));
        StaticCache::put(array('channel', '', 'entry'), array('id' => 42));
        self::assertSame(array('id' => 42), StaticCache::get('channel:entry'));
        self::assertTrue(StaticCache::has('channel:entry'));
        StaticCache::set('raw', 'value', true);
        self::assertSame('value', StaticCache::get('raw', true));
        self::assertSame(array(sha1('channel:entry') => array('id' => 42), 'raw' => 'value'), StaticCache::all());
        StaticCache::delete('channel:entry');
        self::assertFalse(StaticCache::has('channel:entry'));
        self::assertTrue(StaticCache::clear());
        self::assertSame(array(), StaticCache::all());
    }

    /**
     * Preserve a numeric zero component independently of another cache entry.
     *
     * @param int|string $zero
     * @return void
     * @dataProvider zeroKeyComponents
     */
    public function testZeroComponentPreservesCompositeKeyIdentity($zero): void
    {
        StaticCache::put('entry', 'original');
        StaticCache::put(array('entry', $zero), 'zero');

        self::assertSame(array('original', 'zero'), array(StaticCache::get('entry'), StaticCache::get(array('entry', $zero))));
    }

    /**
     * Include integer and database-style numeric-string key components.
     *
     * @return array
     */
    public static function zeroKeyComponents(): array
    {
        return array('integer zero' => array(0), 'numeric string zero' => array('0'));
    }
}
