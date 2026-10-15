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

use Itc\Promotion\Api\Data\StorefrontProductListInterface;
use Magento\Framework\Api\AbstractSimpleObject;

class StorefrontProductList extends AbstractSimpleObject implements StorefrontProductListInterface
{
    /**
     * @inheritdoc
     */
    public function getItems(): array
    {
        $items = $this->_get('items');
        return is_array($items) ? $items : [];
    }

    /**
     * @inheritdoc
     */
    public function setItems(array $items): StorefrontProductListInterface
    {
        return $this->setData('items', $items);
    }
}
