<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Api\Data;

/**
 * Public promotion response.
 */
interface StorefrontProductListInterface
{
    /**
     * Returns the items.
     *
     * @return \Itc\Promotion\Api\Data\StorefrontProductInterface[]
     */
    public function getItems(): array;

    /**
     * Sets the items.
     *
     * @param \Itc\Promotion\Api\Data\StorefrontProductInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
