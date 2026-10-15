<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Test\Unit\Model\Pricing;

use Itc\Promotion\Model\Pricing\ProductPriceReader;
use Magento\Catalog\Model\Product;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceInfoInterface;
use PHPUnit\Framework\TestCase;

class ProductPriceReaderTest extends TestCase
{
    /**
     * Verifies missing Special Price Is Null.
     *
     * @return void
     */
    public function testMissingSpecialPriceIsNull(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getData')->with('special_price')->willReturn(null);
        $this->assertNull((new ProductPriceReader())->getSpecialPrice($product));
    }

    /**
     * Verifies configured Special Price Is Returned Without Discount Math.
     *
     * @return void
     */
    public function testConfiguredSpecialPriceIsReturnedWithoutDiscountMath(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getData')->with('special_price')->willReturn('80.0000');
        $this->assertSame(80.0, (new ProductPriceReader())->getSpecialPrice($product));
    }

    /**
     * Verifies regular And Final Prices Come From The Native Price Model.
     *
     * @return void
     */
    public function testRegularAndFinalPricesComeFromTheNativePriceModel(): void
    {
        $regular = $this->amount(100.0);
        $final = $this->amount(75.0);
        $regularPrice = $this->createMock(PriceInterface::class);
        $regularPrice->method('getAmount')->willReturn($regular);
        $finalPrice = $this->createMock(PriceInterface::class);
        $finalPrice->method('getAmount')->willReturn($final);
        $info = $this->createMock(PriceInfoInterface::class);
        $info->method('getPrice')->willReturnCallback(
            static function (string $code) use ($regularPrice, $finalPrice): PriceInterface {
                return $code === 'final_price' ? $finalPrice : $regularPrice;
            }
        );
        $product = $this->createMock(Product::class);
        $product->method('getPriceInfo')->willReturn($info);

        $reader = new ProductPriceReader();
        $this->assertSame(100.0, $reader->getRegularPrice($product));
        $this->assertSame(75.0, $reader->getFinalPrice($product));
    }

    /**
     * Amount.
     *
     * @param float $value
     * @return AmountInterface
     */
    private function amount(float $value): AmountInterface
    {
        $amount = $this->createMock(AmountInterface::class);
        $amount->method('getValue')->willReturn($value);
        return $amount;
    }
}
