<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PromotionItem extends AbstractDb
{
    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init('itc_promotion_item', 'entity_id');
    }
}
