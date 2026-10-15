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

use Magento\Framework\ObjectManagerInterface;

class CollectionFactory
{
    /**
     * Constructor.
     *
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(private readonly ObjectManagerInterface $objectManager)
    {
    }

    /**
     * Create.
     *
     * @param array $data
     * @return Collection
     */
    public function create(array $data = []): Collection
    {
        return $this->objectManager->create(Collection::class, $data);
    }
}
