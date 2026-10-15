<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Test\Unit\Model\Resolver;

use Itc\Promotion\Model\Eligibility\StorefrontEligibility;
use Itc\Promotion\Model\Media\ProductImageUrl;
use Itc\Promotion\Model\Pricing\ProductPriceReader;
use Itc\Promotion\Model\Resolver\StorefrontProductResolver;
use Itc\Promotion\Model\StorefrontProductFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Customer\Model\Group as CustomerGroup;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceInfoInterface;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Api\Data\IsProductSalableResultInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class StorefrontProductResolverTest extends TestCase
{
    /**
     * Verifies guest price data, emulated currency, a missing special price, and exclusion rules.
     *
     * @return void
     */
    public function testGuestPricesUseTheEmulatedStoreCurrencyAndSkipIneligibleProducts(): void
    {
        $enabled = $this->product('24-MB01', Status::STATUS_ENABLED, 100.0, 75.0, null);
        $disabled = $this->product('DISABLED', Status::STATUS_DISABLED, 10.0, 10.0, null);
        $unsalable = $this->product('UNSALABLE', Status::STATUS_ENABLED, 20.0, 20.0, null);

        $collection = $this->createMock(Collection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$enabled, $disabled, $unsalable]));
        $collection->expects($this->once())
            ->method('addPriceData')
            ->with(CustomerGroup::NOT_LOGGED_IN_ID, 2);
        $collection->expects($this->once())->method('addWebsiteNamesToResult');

        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getCode')->willReturn('hyva');

        $storeBefore = $this->createMock(Store::class);
        $storeBefore->method('getId')->willReturn(2);
        $storeBefore->method('getWebsiteId')->willReturn(2);
        $storeBefore->method('getWebsite')->willReturn($website);
        $storeBefore->method('getCurrentCurrencyCode')->willReturn('USD');

        $groupId = 3;
        $currencyGroup = null;
        $storeAfter = $this->createMock(Store::class);
        $storeAfter->method('getCurrentCurrencyCode')->willReturnCallback(
            static function () use (&$groupId, &$currencyGroup): string {
                $currencyGroup = $groupId;
                return 'EUR';
            }
        );

        $emulated = false;
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturnCallback(
            static function () use (&$emulated, $storeBefore, $storeAfter) {
                return $emulated ? $storeAfter : $storeBefore;
            }
        );

        $stock = $this->createMock(StockInterface::class);
        $stock->method('getStockId')->willReturn(2);
        $stockResolver = $this->createMock(StockResolverInterface::class);
        $stockResolver->method('execute')->willReturn($stock);

        $salable = $this->createMock(AreProductsSalableInterface::class);
        $salable->method('execute')->willReturn([
            $this->salableResult('24-MB01', true),
            $this->salableResult('DISABLED', true),
            $this->salableResult('UNSALABLE', false),
        ]);

        $emulation = $this->createMock(Emulation::class);
        $emulation->expects($this->once())
            ->method('startEnvironmentEmulation')
            ->willReturnCallback(static function () use (&$emulated): void {
                $emulated = true;
            });
        $emulation->expects($this->once())->method('stopEnvironmentEmulation');

        $session = $this->createMock(CustomerSession::class);
        $session->method('getCustomerGroupId')->willReturnCallback(
            static function () use (&$groupId): int {
                return $groupId;
            }
        );
        $session->method('setCustomerGroupId')->willReturnCallback(
            static function (int $id) use (&$groupId): void {
                $groupId = $id;
            }
        );

        $images = $this->createMock(ProductImageUrl::class);
        $images->method('get')->willReturn(null);

        $resolver = new StorefrontProductResolver(
            $collectionFactory,
            $storeManager,
            $stockResolver,
            $salable,
            new StorefrontEligibility(),
            new ProductPriceReader(),
            new StorefrontProductFactory(),
            $emulation,
            $images,
            $session
        );

        $items = $resolver->resolve(['24-MB01', 'DISABLED', 'UNSALABLE']);

        $this->assertCount(1, $items);
        $this->assertSame('24-MB01', $items[0]->getSku());
        $this->assertSame(100.0, $items[0]->getPrice());
        $this->assertNull($items[0]->getSpecialPrice());
        $this->assertSame(75.0, $items[0]->getFinalPrice());
        $this->assertSame('EUR', $items[0]->getCurrency());
        $this->assertNull($items[0]->getImage());
        $this->assertSame(CustomerGroup::NOT_LOGGED_IN_ID, $currencyGroup);
        $this->assertSame(3, $groupId);
    }

    /**
     * Builds a product double with native price-info values.
     *
     * @param string $sku
     * @param int $status
     * @param float $regular
     * @param float $final
     * @param float|null $special
     * @return Product
     */
    private function product(
        string $sku,
        int $status,
        float $regular,
        float $final,
        ?float $special
    ): Product {
        $regularAmount = $this->createMock(AmountInterface::class);
        $regularAmount->method('getValue')->willReturn($regular);
        $finalAmount = $this->createMock(AmountInterface::class);
        $finalAmount->method('getValue')->willReturn($final);
        $regularPrice = $this->createMock(PriceInterface::class);
        $regularPrice->method('getAmount')->willReturn($regularAmount);
        $finalPrice = $this->createMock(PriceInterface::class);
        $finalPrice->method('getAmount')->willReturn($finalAmount);
        $info = $this->createMock(PriceInfoInterface::class);
        $info->method('getPrice')->willReturnCallback(
            static function (string $code) use ($regularPrice, $finalPrice): PriceInterface {
                return $code === 'final_price' ? $finalPrice : $regularPrice;
            }
        );

        $product = $this->createMock(Product::class);
        $product->method('getSku')->willReturn($sku);
        $product->method('getId')->willReturn(1);
        $product->method('getName')->willReturn($sku);
        $product->method('getStatus')->willReturn($status);
        $product->method('getWebsiteIds')->willReturn([2]);
        $product->method('getPriceInfo')->willReturn($info);
        $product->method('getData')->willReturnCallback(
            static function (string $key) use ($special): mixed {
                return $key === 'special_price' ? $special : null;
            }
        );
        return $product;
    }

    /**
     * Builds one MSI salability result.
     *
     * @param string $sku
     * @param bool $isSalable
     * @return IsProductSalableResultInterface
     */
    private function salableResult(string $sku, bool $isSalable): IsProductSalableResultInterface
    {
        $result = $this->createMock(IsProductSalableResultInterface::class);
        $result->method('getSku')->willReturn($sku);
        $result->method('isSalable')->willReturn($isSalable);
        return $result;
    }
}
