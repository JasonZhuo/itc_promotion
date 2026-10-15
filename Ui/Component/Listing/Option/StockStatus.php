<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Ui\Component\Listing\Option;

use Magento\Framework\Data\OptionSourceInterface;

class StockStatus implements OptionSourceInterface
{
    /**
     * Default catalog stock status values.
     *
     * @return array<int, array{value: int, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 1, 'label' => __('In Stock')],
            ['value' => 0, 'label' => __('Out of Stock')],
        ];
    }
}
