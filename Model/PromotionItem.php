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

use Itc\Promotion\Model\ResourceModel\PromotionItem as PromotionItemResource;
use Magento\Framework\Model\AbstractModel;

class PromotionItem extends AbstractModel
{
    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(PromotionItemResource::class);
    }

    /**
     * @inheritdoc
     */
    public function getSku(): string
    {
        return (string) $this->getData('sku');
    }

    /**
     * @inheritdoc
     */
    public function getPosition(): int
    {
        return (int) $this->getData('position');
    }
}
