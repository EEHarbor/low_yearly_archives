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
}
