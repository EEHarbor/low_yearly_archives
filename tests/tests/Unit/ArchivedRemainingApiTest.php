<?php

use Low\YearlyArchives\FluxCapacitor\Base\Mcp;
use Low\YearlyArchives\FluxCapacitor\Base\Upd;
use Low\YearlyArchives\FluxCapacitor\Conduit\StaticCache;

final class LowYearlyModelCollection
{
    private $total;

    /**
     * Store the number of records returned by an EE model collection.
     *
     * @param int $total
     * @return void
     */
    public function __construct($total)
    {
        $this->total = $total;
    }

    /**
     * Return the collection's record count.
     *
     * @return int
     */
    public function count()
    {
        return $this->total;
    }
}

final class LowYearlyModelQuery
{
    public $calls = array();

    /**
     * Return a record collection without mutating the original query's limits.
     *
     * @return LowYearlyModelCollection
     */
    public function all()
    {
        $this->calls[] = array('all', array());

        return new LowYearlyModelCollection(42);
    }

    /**
     * Apply an EE model result limit and retain fluent chaining.
     *
     * @param int $limit
     * @return self
     */
    public function limit($limit)
    {
        $this->calls[] = array('limit', array($limit));

        return $this;
    }

    /**
     * Apply an EE model result offset and retain fluent chaining.
     *
     * @param int $offset
     * @return self
     */
    public function offset($offset)
    {
        $this->calls[] = array('offset', array($offset));

        return $this;
    }
}

final class ArchivedRemainingApiTest extends LowYearlyTestCase
{
    /**
     * Construct the updater through the real archived license/version services.
     *
     * @return void
     */
    public function testUpdaterConstructorLoadsVersionAndAddsUpdatePixel(): void
    {
        StaticCache::put('verifyLicenseTable', false);
        $this->ee->cp = $this->getMockBuilder(stdClass::class)->addMethods(array('get_foot', 'add_to_foot'))->getMock();
        $this->ee->cp->method('get_foot')->willReturn(array());
        $footer = array();
        $this->ee->cp->method('add_to_foot')->willReturnCallback(function ($html) use (&$footer) { $footer[] = $html; });

        $updater = new Upd();

        self::assertSame('4.1.1', $updater->version);
        self::assertSame(Upd::class, $updater->module_name);
        self::assertCount(2, $footer);
        self::assertStringContainsString('id="low_yearly_archives__up"', $footer[0]);
        self::assertStringContainsString('<script type="text/javascript">', $footer[1]);
    }

    /**
     * Reject source diagnostics when loading the shipped navigation conduit.
     *
     * @return void
     */
    public function testNavigationConduitLoadsWithoutMissingFileDiagnostics(): void
    {
        self::assertTrue(class_exists('Low\\YearlyArchives\\FluxCapacitor\\Conduit\\McpNav'));
    }

    /**
     * Count on a cloned model, render links and apply the original query window.
     *
     * @param mixed $page
     * @param string|null $urlPath
     * @param int $expectedPage
     * @return void
     * @dataProvider paginationInputs
     */
    public function testMcpPaginationPreservesOriginalCountQuery($page, $urlPath, $expectedPage): void
    {
        $this->ee->input->values['custom_page'] = $page;
        $pagination = $this->getMockBuilder(stdClass::class)->addMethods(array('perPage', 'currentPage', 'displayPageLinks', 'queryStringVariable', 'render'))->getMock();
        $pagination->expects(self::once())->method('perPage')->with(10)->willReturnSelf();
        $pagination->expects(self::once())->method('currentPage')->with($expectedPage)->willReturnSelf();
        $pagination->expects(self::once())->method('displayPageLinks')->with(7)->willReturnSelf();
        $pagination->expects(self::once())->method('queryStringVariable')->with('custom_page')->willReturnSelf();
        $url = $urlPath === null ? null : new LowYearlyUrl($urlPath);
        $urlConstraint = self::identicalTo($url);
        if ($url === null) {
            $expectedPath = 'addons/settings/low_yearly_archives/' . __FUNCTION__;
            $urlConstraint = self::callback(function ($value) use ($expectedPath) {
                return $value instanceof LowYearlyUrl && $value->path === $expectedPath;
            });
        }
        $pagination->expects(self::once())->method('render')->with($urlConstraint)->willReturn('pagination links');
        $this->ee->services['CP/Pagination'] = $pagination;
        $mcp = new Mcp();
        $model = new LowYearlyModelQuery();

        self::assertSame($model, $mcp->paginateModel($model, $url, 10, 7, 'custom_page'));
        self::assertSame('pagination links', $mcp->data['pagination']);
        self::assertSame(array(array('limit', array(10)), array('offset', array(10 * ($expectedPage - 1)))), $model->calls);
        self::assertContains(array('CP/Pagination', 42), $this->ee->service_calls);
    }

    /**
     * Supply missing/zero/numeric-string inputs and explicit/inferred URLs.
     *
     * @return array
     */
    public static function paginationInputs(): array
    {
        return array(array(null, null, 1), array('0', 'listing', 1), array('3', 'listing', 3));
    }
}
