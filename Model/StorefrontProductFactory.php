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

class StorefrontProductFactory
{
    /**
     * Create.
     *
     * @param array $data
     * @return StorefrontProduct
     */
    public function create(array $data = []): StorefrontProduct
    {
        return new StorefrontProduct($data);
    }
}
