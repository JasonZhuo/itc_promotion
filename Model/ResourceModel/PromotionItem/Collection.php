<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Model\ResourceModel\PromotionItem;

use Itc\Promotion\Model\PromotionItem;
use Itc\Promotion\Model\ResourceModel\PromotionItem as PromotionItemResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(PromotionItem::class, PromotionItemResource::class);
    }
}
