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
 * Result of a successful full-list replacement.
 */
interface ReplaceResultInterface
{
    /**
     * Returns the skus.
     *
     * @return string[]
     */
    public function getSkus(): array;

    /**
     * Sets the skus.
     *
     * @param string[] $skus
     * @return $this
     */
    public function setSkus(array $skus): self;

    /**
     * Returns the count.
     *
     * @return int
     */
    public function getCount(): int;

    /**
     * Sets the count.
     *
     * @param int $count
     * @return $this
     */
    public function setCount(int $count): self;
}
