<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Model\Pricing;

use Magento\Catalog\Model\Product;

/**
 * Reads native catalog prices. Does not invent discount math.
 */
class ProductPriceReader
{
    /**
     * Returns the native regular price.
     *
     * @param Product $product
     * @return float
     */
    public function getRegularPrice(Product $product): float
    {
        return (float) $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
    }

    /**
     * Returns the native final price.
     *
     * @param Product $product
     * @return float
     */
    public function getFinalPrice(Product $product): float
    {
        return (float) $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
    }

    /**
     * Returns the raw special price attribute.
     *
     * Null when the attribute is empty. Date-range activity is reflected by final_price, not by this value.
     *
     * @param Product $product
     * @return float|null
     */
    public function getSpecialPrice(Product $product): ?float
    {
        $value = $product->getData('special_price');
        if ($value === null || $value === '' || $value === false) {
            return null;
        }
        return (float) $value;
    }
}
