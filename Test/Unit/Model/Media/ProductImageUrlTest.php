<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Test\Unit\Model\Media;

use Itc\Promotion\Model\Media\ProductImageUrl;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProductImageUrlTest extends TestCase
{
    /**
     * Verifies missing Image Helper Failure Returns Null.
     *
     * @return void
     */
    public function testMissingImageHelperFailureReturnsNull(): void
    {
        $helper = $this->createMock(ImageHelper::class);
        $helper->method('init')->willThrowException(new \RuntimeException('no image config'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');
        $product = $this->createMock(Product::class);
        $product->method('getData')->willReturnCallback(
            static fn (string $key): mixed => $key === 'image' ? '/a.jpg' : 'NO-IMAGE'
        );
        $product->method('getSku')->willReturn('NO-IMAGE');

        $this->assertNull((new ProductImageUrl($helper, $logger))->get($product));
    }

    /**
     * Verifies empty Image Url Returns Null.
     *
     * @return void
     */
    public function testEmptyImageUrlReturnsNull(): void
    {
        $helper = $this->createMock(ImageHelper::class);
        $helper->method('init')->willReturnSelf();
        $helper->method('getUrl')->willReturn('');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $product = $this->createMock(Product::class);
        $product->method('getData')->with('image')->willReturn('/a.jpg');

        $this->assertNull((new ProductImageUrl($helper, $logger))->get($product));
    }

    /**
     * Verifies image Url Is Returned.
     *
     * @return void
     */
    public function testImageUrlIsReturned(): void
    {
        $helper = $this->createMock(ImageHelper::class);
        $helper->method('init')->willReturnSelf();
        $helper->method('getUrl')->willReturn('https://example.test/media/catalog/product/a.jpg');
        $logger = $this->createMock(LoggerInterface::class);

        $product = $this->createMock(Product::class);
        $product->method('getData')->with('image')->willReturn('/a.jpg');

        $this->assertSame(
            'https://example.test/media/catalog/product/a.jpg',
            (new ProductImageUrl($helper, $logger))->get($product)
        );
    }

    /**
     * Verifies a product with no assigned image returns null and does not build a placeholder URL.
     *
     * @return void
     */
    public function testMissingAssignedImageReturnsNull(): void
    {
        $helper = $this->createMock(ImageHelper::class);
        $helper->expects($this->never())->method('init');
        $logger = $this->createMock(LoggerInterface::class);
        $product = $this->createMock(Product::class);
        $product->method('getData')->with('image')->willReturn('no_selection');

        $this->assertNull((new ProductImageUrl($helper, $logger))->get($product));
    }
}
