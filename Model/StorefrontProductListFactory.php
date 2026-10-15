<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Model;

class StorefrontProductListFactory
{
    /**
     * Create.
     *
     * @param array $data
     * @return StorefrontProductList
     */
    public function create(array $data = []): StorefrontProductList
    {
        return new StorefrontProductList($data);
    }
}
