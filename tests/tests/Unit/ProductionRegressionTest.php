<?php

use Low\YearlyArchives\FluxCapacitor\Base\Ext;
use Low\YearlyArchives\FluxCapacitor\Base\Mod;
use Low\YearlyArchives\FluxCapacitor\Base\Pi;
use Low\YearlyArchives\FluxCapacitor\Conduit\StaticCache;

final class ProductionRegressionTest extends LowYearlyTestCase
{
    /**
     * Require the documented plugin metadata API to return usable metadata.
     *
     * @return void
     */
    public function testStaticPluginInfoReturnsMetadata(): void
    {
        $info = Pi::info();

        self::assertIsArray($info);
        self::assertSame('Low Yearly Archives', $info['pi_name']);
    }

    /**
     * Preserve every stored false-like value rather than returning a cache miss.
     *
     * @param mixed $value
     * @return void
     * @dataProvider falseLikeCacheValues
     */
    public function testStaticCacheRetainsFalseLikeValues($value): void
    {
        StaticCache::put('value', $value);

        self::assertSame($value, StaticCache::get('value'));
    }

    /**
     * Supply scalar and container values that are false in a boolean context.
     *
     * @return array
     */
    public static function falseLikeCacheValues(): array
    {
        return array('zero' => array(0), 'empty array' => array(array()), 'null' => array(null), 'empty string' => array(''), 'false' => array(false));
    }

    /**
     * Report success when an existing cache value has been removed.
     *
     * @return void
     */
    public function testStaticCacheDeleteReportsSuccess(): void
    {
        StaticCache::put('stored', 'value');

        self::assertTrue(StaticCache::delete('stored'));
    }

    /**
     * Preserve legacy alias arguments rather than resetting their values.
     *
     * @return void
     */
    public function testLegacyExtensionAliasPreservesHookPriorityAndEnabled(): void
    {
        $extension = new Ext();
        $this->invoke($extension, 'register_extension', array('publish', 'custom_hook', 2, 'n'));
        $insert = end($this->ee->db->calls)[1][1];

        self::assertSame('custom_hook', $insert['hook']);
        self::assertSame(2, $insert['priority']);
        self::assertSame('n', $insert['enabled']);
    }

    /**
     * Reject source deprecations for a null helper input on modern PHP versions.
     *
     * @return void
     */
    public function testNullParameterDoesNotEmitSourceDeprecation(): void
    {
        require_once PATH_THIRD . 'low_yearly_archives/pi.low_yearly_archives.php';
        $plugin = (new ReflectionClass(Low_yearly_archives::class))->newInstanceWithoutConstructor();

        self::assertSame(array(array(''), true), $this->invoke($plugin, '_explode_param', array(null)));
    }

    /**
     * Keep null field parameters safe for PHP's internal string functions.
     *
     * @return void
     */
    public function testNullModuleParameterDoesNotEmitSourceDeprecation(): void
    {
        $this->ee->TMPL->tagparams['value'] = null;

        self::assertSame('fallback', $this->invoke(new Mod(), 'getParameter', array('value', 'fallback')));
    }
}
