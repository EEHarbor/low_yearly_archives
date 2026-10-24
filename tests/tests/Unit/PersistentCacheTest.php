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
            if ($file->isLink()) { unlink($file->getPathname()); continue; }
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
     * Supply absolute/relative missing aliases and aliases to existing directories.
     *
     * @return array
     */
    public static function writableCacheAliases(): array
    {
        return array(array('cache', false, false), array('cache', true, false),
            array('module', false, false), array('module', true, false),
            array('cache', false, true), array('module', false, true));
    }

    /**
     * Preserve native configured alias creation, exact bytes and disk reads after reset.
     *
     * @dataProvider writableCacheAliases
     * @param string $scope Generic or module cache path.
     * @param bool $relative Whether the alias target is relative.
     * @param bool $existing Whether the target directory already exists.
     * @return void
     */
    public function testWritableCacheAliasesRemainSupported($scope, $relative, $existing): void
    {
        mkdir($this->temporary . '/target', 0700);
        $target = $this->temporary . '/target/cache';
        if ($existing) { mkdir($target, 0700); }
        $alias = $relative ? ($scope === 'cache' ? 'target/cache' : '../target/cache') : $target;
        $link = $this->linkCachePath($scope, $alias);
        $value = array('zero' => 0, 'name' => 'saved');
        self::assertTrue(PersistentCache::set('entry', $value));
        $file = $target . ($scope === 'cache' ? '/low_yearly_archives' : '') . '/' . sha1('entry');
        self::assertSame(serialize($value), file_get_contents($file));
        StaticCache::clear();
        self::assertSame($value, PersistentCache::get('entry'));
        self::assertTrue(is_link($link));
        self::assertDirectoryExists($target);
    }

    /**
     * Supply native accepted trailing-target chains at each configured boundary.
     *
     * @return array
     */
    public static function trailingCacheAliases(): array
    {
        $cases = array();
        foreach (array('cache', 'module') as $scope) {
            foreach (array('first', 'both', 'three') as $shape) { $cases[] = array($scope, $shape); }
        }
        return $cases;
    }

    /**
     * Preserve native alias spelling, stored bytes and disk reads after fixture setup.
     *
     * @dataProvider trailingCacheAliases
     * @param string $scope Generic or module cache directory.
     * @param string $shape Intermediate targets retaining their trailing separator.
     * @return void
     */
    public function testTrailingCacheAliasTargetsRemainSupported($scope, $shape): void
    {
        mkdir($this->temporary . '/target', 0700);
        $target = $this->temporary . '/target/cache';
        $inner = $this->temporary . '/inner';
        $next = $shape === 'three' ? $this->temporary . '/third/' : $target . ($shape === 'both' ? '/' : '');
        symlink($next, $inner);
        if ($shape === 'three') { symlink($target, $this->temporary . '/third'); }
        $link = $this->linkCachePath($scope, $scope === 'cache' ? 'inner/' : '../inner/');
        // Newly created link chains can leave PHP's realpath cache stale before the source runs.
        clearstatcache(true);
        $handler = static function () {};
        $before = set_error_handler($handler);
        restore_error_handler();
        $value = array('zero' => 0, 'name' => 'saved');
        self::assertTrue(PersistentCache::set('entry', $value));
        $after = set_error_handler($handler);
        restore_error_handler();
        self::assertSame($before, $after);
        $file = $target . ($scope === 'cache' ? '/low_yearly_archives' : '') . '/' . sha1('entry');
        self::assertSame(serialize($value), file_get_contents($file));
        StaticCache::clear();
        self::assertSame($value, PersistentCache::get('entry'));
        self::assertSame($value, PersistentCache::get('entry'));
        self::assertTrue(PersistentCache::has('entry'));
        self::assertTrue(is_link($link));
        self::assertTrue(is_link($inner));
        self::assertDirectoryExists($target);
    }

    /**
     * Supply unavailable final parents and incomplete trailing-target chains.
     *
     * @return array
     */
    public static function blockedTrailingCacheAliases(): array
    {
        $cases = array();
        foreach (array('cache', 'module') as $scope) {
            foreach (array('readonly', 'nonsearchable', 'file', 'missing', 'cycle', 'third-without-separator') as $shape) {
                $cases[] = array($scope, $shape);
            }
        }
        return $cases;
    }

    /**
     * Keep unavailable final targets on the quiet false and developer-log path.
     *
     * @dataProvider blockedTrailingCacheAliases
     * @param string $scope Generic or module cache directory.
     * @param string $shape Unavailable final parent or alias spelling.
     * @return void
     */
    public function testBlockedTrailingCacheAliasesReturnFalse($scope, $shape): void
    {
        $parent = $this->temporary . '/target';
        mkdir($parent, 0700);
        $target = $parent . '/cache';
        $inner = $this->temporary . '/inner';
        $link = $this->linkCachePath($scope, $inner . '/');
        $next = $shape === 'cycle' ? $link . '/' : $target;
        if ($shape === 'third-without-separator') {
            $next = $this->temporary . '/third';
            symlink($target, $next);
        }
        symlink($next, $inner);
        if ($shape === 'readonly') { chmod($parent, 0500); }
        if ($shape === 'nonsearchable') { chmod($parent, 0200); }
        if ($shape === 'missing') { rmdir($parent); }
        if ($shape === 'file') { rmdir($parent); file_put_contents($parent, 'retained'); chmod($parent, 0700); }
        clearstatcache();
        $handler = static function () {};
        $before = set_error_handler($handler);
        restore_error_handler();
        try {
            self::assertFalse(PersistentCache::set('entry', 'value'));
            $after = set_error_handler($handler);
            restore_error_handler();
            self::assertSame($before, $after);
            self::assertCount(1, $this->ee->logger->calls);
        } finally {
            if (file_exists($parent)) { chmod($parent, 0700); }
        }
        self::assertTrue(is_link($link));
        self::assertTrue(is_link($inner));
    }

    /**
     * Supply unavailable alias target shapes at both configured cache boundaries.
     *
     * @return array
     */
    public static function blockedCacheAliases(): array
    {
        $cases = array();
        foreach (array('cache', 'module') as $scope) {
            foreach (array('two-hop', 'cycle', 'missing-parent', 'readonly-parent', 'nonsearchable-parent', 'file') as $kind) {
                $cases[] = array($scope, $kind);
            }
        }
        return $cases;
    }

    /**
     * Keep unavailable cache aliases on the quiet false and developer-log path.
     *
     * @dataProvider blockedCacheAliases
     * @param string $scope Generic or module cache path.
     * @param string $kind Unavailable target shape.
     * @return void
     */
    public function testBlockedCacheAliasesReturnFalse($scope, $kind): void
    {
        $parent = $this->temporary . '/target';
        mkdir($parent, 0700);
        $target = $parent . '/cache';
        $alias = $target;
        if ($kind === 'two-hop' || $kind === 'cycle') { $alias = $this->temporary . '/second'; }
        if ($kind === 'missing-parent') { $alias = $this->temporary . '/absent/cache'; }
        if ($kind === 'file') { file_put_contents($target, 'retained'); }
        $link = $this->linkCachePath($scope, $alias);
        if ($kind === 'two-hop' || $kind === 'cycle') { symlink($kind === 'cycle' ? $link : $target, $alias); }
        if ($kind === 'readonly-parent') { chmod($parent, 0500); }
        if ($kind === 'nonsearchable-parent') { chmod($parent, 0200); }
        clearstatcache();
        $message = 'Low Yearly Archives: Write Cache enabled but could not create '
            . ($scope === 'cache' ? 'cache folder: ' : 'module cache folder: ')
            . $this->temporary . '/cache/' . ($scope === 'cache' ? '' : 'low_yearly_archives/');
        try {
            self::assertFalse(PersistentCache::set('entry', 'value'));
            self::assertSame(array(array('developer', array($message))), $this->ee->logger->calls);
        } finally {
            chmod($parent, 0700);
        }
        self::assertTrue(is_link($link));
        self::assertDirectoryDoesNotExist($target);
    }

    /**
     * Supply every guarded public operation for existing non-searchable directories.
     *
     * @return array
     */
    public static function nonsearchableCachePaths(): array
    {
        $cases = array();
        foreach (array('cache', 'module') as $scope) {
            foreach (array('get', 'set', 'delete', 'clear') as $method) { $cases[] = array($scope, $method); }
        }
        return $cases;
    }

    /**
     * Reject existing writable cache directories before traversing or writing through them.
     *
     * @dataProvider nonsearchableCachePaths
     * @param string $scope Generic or module cache path.
     * @param string $method Guarded public operation.
     * @return void
     */
    public function testExistingCacheDirectoriesRequireSearchPermission($scope, $method): void
    {
        mkdir($this->temporary . '/cache/low_yearly_archives', 0700, true);
        $path = $this->temporary . '/cache' . ($scope === 'cache' ? '' : '/low_yearly_archives');
        chmod($path, 0200);
        clearstatcache();
        $message = 'Low Yearly Archives: Write Cache enabled but '
            . ($scope === 'cache' ? 'cache path not writable: ' : 'module cache path not writable: ') . $path . '/';
        $arguments = array('entry');
        if ($method === 'set') { $arguments[] = 'value'; }
        if ($method === 'clear') { $arguments = array(); }
        try {
            self::assertTrue(is_writable($path));
            self::assertFalse(is_executable($path));
            self::assertFalse(call_user_func_array(array(PersistentCache::class, $method), $arguments));
            self::assertSame(array(array('developer', array($message))), $this->ee->logger->calls);
        } finally {
            chmod($path, 0700);
        }
    }

    /**
     * Link only this test's configured generic or module directory to its owned target.
     *
     * @param string $scope Generic or module cache path.
     * @param string $target Absolute or relative native target.
     * @return string Configured alias path.
     */
    private function linkCachePath($scope, $target): string
    {
        if ($scope === 'module') { mkdir($this->temporary . '/cache', 0700); }
        $link = $this->temporary . '/cache' . ($scope === 'cache' ? '' : '/low_yearly_archives');
        symlink($target, $link);
        return $link;
    }

    /**
     * Reject writable parents without directory search permission before creating children.
     *
     * @return void
     */
    public function testCacheParentRequiresSearchPermission(): void
    {
        $parent = $this->temporary;
        chmod($parent, 0600);
        clearstatcache();
        try {
            self::assertTrue(is_writable($parent));
            self::assertFalse(is_executable($parent));
            self::assertFalse(PersistentCache::set('entry', 'value'));
            self::assertSame(array(array('developer', array('Low Yearly Archives: Write Cache enabled but could not create cache folder: ' . $parent . '/cache/'))), $this->ee->logger->calls);
        } finally {
            chmod($parent, 0700);
        }
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
        StaticCache::put(sha1('raw-key'), array('present'), true);

        self::assertSame(array('present'), PersistentCache::get('raw-key'));
        self::assertSame(array(), $this->ee->load->calls);
    }

    /**
     * Keep logical hash-looking keys independent of another entry's request-cache key.
     *
     * @return void
     */
    public function testHashLookingLogicalKeyDoesNotAliasAnotherEntry(): void
    {
        self::assertTrue(PersistentCache::set('entry', 'plain'));
        self::assertTrue(PersistentCache::set(sha1('entry'), 'hashed'));
        self::assertSame('plain', PersistentCache::get('entry'));
        self::assertSame('hashed', PersistentCache::get(sha1('entry')));
    }

    /**
     * Preserve false-like disk values and decoded request-cache hits after a reset.
     *
     * @return void
     */
    public function testFalseLikeDiskAndWarmReadsRemainTyped(): void
    {
        foreach (array(null, false, 0, '0', '', array()) as $value) {
            self::assertTrue(PersistentCache::set('entry', $value));
            StaticCache::clear();
            self::assertSame($value, PersistentCache::get('entry'));
            self::assertSame($value, PersistentCache::get('entry'));
        }
    }

    /**
     * Read historical storage while retaining its exact disk bytes.
     *
     * @return void
     */
    public function testLegacyDiskStorageRemainsReadableWithoutRewriting(): void
    {
        foreach (array('a:1:{s:4:"name";S:1:"\\41";}', 'a:1:{s:4:"name";s:1:"A";}unused') as $bytes) {
            self::assertTrue(PersistentCache::set('entry', array()));
            $file = $this->temporary . '/cache/low_yearly_archives/' . sha1('entry');
            file_put_contents($file, $bytes);
            StaticCache::clear();
            self::assertSame(array('name' => 'A'), PersistentCache::get('entry'));
            self::assertSame(array('name' => 'A'), PersistentCache::get('entry'));
            self::assertSame($bytes, file_get_contents($file));
        }
    }

    /**
     * Read a documented composite key in either its array or scalar form.
     *
     * @return void
     */
    public function testCompositeKeyRoundTrip(): void
    {
        self::assertTrue(PersistentCache::set(array('channel', 'entry'), 'value'));
        self::assertSame('value', PersistentCache::get('channel:entry'));
        self::assertSame('value', PersistentCache::get(array('channel', 'entry')));
    }

    /**
     * Recognize an existing file when the caller explicitly supplies a processed key.
     *
     * @return void
     */
    public function testProcessedKeyExistenceFlag(): void
    {
        self::assertTrue(PersistentCache::set('entry', 'value'));
        self::assertTrue(PersistentCache::has('entry'));
        self::assertTrue(PersistentCache::has(sha1('entry'), true));
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
