<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Model\Media;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Psr\Log\LoggerInterface;

/**
 * Builds a storefront image URL. A missing or broken image becomes null instead of a failed response.
 */
class ProductImageUrl
{
    /**
     * Constructor.
     *
     * @param ImageHelper $imageHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ImageHelper $imageHelper,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Returns the storefront image URL.
     *
     * @param Product $product
     * @return ?string
     */
    public function get(Product $product): ?string
    {
        $image = $product->getData('image');
        if (!is_string($image) || $image === '' || $image === 'no_selection') {
            return null;
        }
        try {
            $url = (string) $this->imageHelper->init($product, 'product_base_image')->getUrl();
            return $url !== '' ? $url : null;
        } catch (\Throwable $exception) {
            $this->logger->warning('Itc_Promotion could not build a product image URL.', [
                'sku' => $product->getSku(),
                'exception' => $exception->getMessage(),
            ]);
            return null;
        }
    }
}
