<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Test\Unit\Model\Catalog;

use Itc\Promotion\Model\Catalog\ExistingSkuChecker;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\InputException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ExistingSkuCheckerTest extends TestCase
{
    /**
     * @var array<int, array{0: string, 1: mixed}>
     */
    private array $whereCalls = [];

    /**
     * Verifies a differently cased request resolves to the stored catalog SKU.
     *
     * @return void
     */
    public function testRequestCasingResolvesToTheCanonicalSku(): void
    {
        $checker = $this->checker(['24-MB01']);
        $this->assertSame(['24-MB01'], $checker->canonicalize(['24-mb01']));
        $this->assertSame([['LOWER(sku) IN (?)', ['24-mb01']]], $this->whereCalls);
    }

    /**
     * Verifies two request casings of one product both resolve to that stored SKU.
     *
     * @return void
     */
    public function testMixedCaseRequestsResolveToTheSameCanonicalSku(): void
    {
        $checker = $this->checker(['24-MB01']);
        $this->assertSame(['24-MB01', '24-MB01'], $checker->canonicalize(['24-mb01', '24-MB01']));
    }

    /**
     * Verifies an unknown SKU is rejected and is not compared with isset on the request text.
     *
     * @return void
     */
    public function testUnknownSkuIsRejected(): void
    {
        $checker = $this->checker(['24-MB01']);
        $this->expectException(InputException::class);
        $checker->canonicalize(['24-mb01', 'missing-sku']);
    }

    /**
     * Verifies an empty request does not query the catalog.
     *
     * @return void
     */
    public function testEmptyListSkipsTheCatalogQuery(): void
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects($this->never())->method('getConnection');
        $checker = new ExistingSkuChecker($resource);
        $this->assertSame([], $checker->canonicalize([]));
    }

    /**
     * Builds a checker whose catalog query returns the given stored SKUs.
     *
     * @param string[] $storedSkus
     * @return ExistingSkuChecker
     */
    private function checker(array $storedSkus): ExistingSkuChecker
    {
        /** @var Select|MockObject $select */
        $this->whereCalls = [];
        $select = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['from', 'where'])
            ->getMock();
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(
            function (string $condition, mixed $value) use ($select): Select {
                $this->whereCalls[] = [$condition, $value];
                return $select;
            }
        );

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchCol')->with($select)->willReturn($storedSkus);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->with('catalog_product_entity')->willReturn('catalog_product_entity');

        return new ExistingSkuChecker($resource);
    }
}
