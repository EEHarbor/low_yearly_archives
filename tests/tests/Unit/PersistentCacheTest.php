<?php

use Low\YearlyArchives\FluxCapacitor\Conduit\PersistentCache;
use Low\YearlyArchives\FluxCapacitor\Conduit\StaticCache;

final class PersistentCacheTest extends LowYearlyTestCase
{
    private $temporary;
    private $originalPaths;

    /**
     * Isolate cache writes and record the real helper/logging boundaries.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->temporary = sys_get_temp_dir() . '/low-yearly-cache-' . bin2hex(random_bytes(8));
        mkdir($this->temporary, 0700);
        $this->originalPaths = array($this->pathProperty('cache_path')->getValue(), $this->pathProperty('module_cache_path')->getValue());
        $this->setPaths($this->temporary . '/cache', $this->temporary . '/cache/low_yearly_archives/');
        $this->ee->load = new LowYearlySpy(array('helper' => null, 'library' => null));
        $this->ee->logger = new LowYearlySpy(array('developer' => null));
    }

    /**
     * Restore class statics and remove only this test's private cache tree.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->setPaths($this->originalPaths[0], $this->originalPaths[1]);
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) {
                chmod($file->getPathname(), 0700);
                rmdir($file->getPathname());
                continue;
            }
            unlink($file->getPathname());
        }
        rmdir($this->temporary);
        parent::tearDown();
    }

    /**
     * Round-trip structured cache data and preserve an unrelated static entry.
     *
     * @return void
     */
    public function testPersistentCacheLifecycle(): void
    {
        self::assertFalse(PersistentCache::has('missing'));
        self::assertFalse(PersistentCache::get('missing'));
        self::assertCount(1, $this->ee->logger->calls);
        $data = array('zero' => 0, 'false' => false, 'null' => null, 'nested' => array(2));
        self::assertTrue(PersistentCache::set(array('channel', 'entry'), $data));
        self::assertTrue(PersistentCache::has('channel:entry'));
        self::assertSame($data, PersistentCache::get('channel:entry'));
        self::assertSame(serialize($data), file_get_contents($this->temporary . '/cache/low_yearly_archives/' . sha1('channel:entry')));
        self::assertTrue(PersistentCache::delete('channel:entry'));
        self::assertFalse(PersistentCache::has('channel:entry'));
        self::assertTrue(PersistentCache::delete('already missing'));
        StaticCache::put('unrelated', 'request data');
        self::assertTrue(PersistentCache::set('first', 'one'));
        self::assertTrue(PersistentCache::set('second', 'two'));
        mkdir($this->temporary . '/cache/low_yearly_archives/subdirectory');
        self::assertTrue(PersistentCache::clear());
        self::assertFalse(PersistentCache::has('first'));
        self::assertFalse(PersistentCache::has('second'));
        self::assertSame('request data', StaticCache::get('unrelated'));
        self::assertDirectoryExists($this->temporary . '/cache/low_yearly_archives/subdirectory');
    }

    /**
     * Return an already-processed request cache value without touching the disk.
     *
     * @return void
     */
    public function testRequestCacheShortCircuit(): void
    {
        StaticCache::put('raw-key', array('present'), true);

        self::assertSame(array('present'), PersistentCache::get('raw-key'));
        self::assertSame(array(), $this->ee->load->calls);
    }

    /**
     * Fail gracefully when the generic or module parent path cannot be created.
     *
     * @param bool $modulePath
     * @param string $operation
     * @return void
     * @dataProvider invalidPaths
     */
    public function testUncreatablePaths($modulePath, $operation): void
    {
        file_put_contents($this->temporary . '/blocker', 'not a directory');
        $cache = $modulePath ? $this->temporary . '/' : $this->temporary . '/blocker/cache';
        $this->setPaths($cache, $this->temporary . '/blocker/module/');

        $arguments = $operation === 'set' ? array('key', 'value') : array('key');
        if ($operation === 'clear') {
            $arguments = array();
        }
        self::assertFalse(call_user_func_array(array(PersistentCache::class, $operation), $arguments));
        self::assertCount(1, $this->ee->logger->calls);
    }

    /**
     * Return false and log when a cache file cannot be opened for writing.
     *
     * @return void
     */
    public function testUnwritableCacheFile(): void
    {
        PersistentCache::delete('key');
        mkdir($this->temporary . '/cache/low_yearly_archives/' . sha1('key'));

        self::assertFalse(PersistentCache::set('key', 'value'));
        self::assertStringContainsString('could not write cache file', $this->ee->logger->calls[0][1][0]);
    }

    /**
     * Supply both path-creation failure locations.
     *
     * @return array
     */
    public static function invalidPaths(): array
    {
        $cases = array();
        foreach (array(false, true) as $modulePath) {
            foreach (array('get', 'set', 'delete', 'clear') as $operation) {
                $cases[] = array($modulePath, $operation);
            }
        }

        return $cases;
    }

    /**
     * Assign private cache roots while retaining their declared source behavior.
     *
     * @param string $cache
     * @param string $module
     * @return void
     */
    private function setPaths($cache, $module): void
    {
        $this->pathProperty('cache_path')->setValue(null, $cache);
        $this->pathProperty('module_cache_path')->setValue(null, $module);
    }

    /**
     * Access a static path property for test isolation.
     *
     * @param string $property
     * @return ReflectionProperty
     */
    private function pathProperty($property)
    {
        $reflection = new ReflectionProperty(PersistentCache::class, $property);
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }

        return $reflection;
    }
}
