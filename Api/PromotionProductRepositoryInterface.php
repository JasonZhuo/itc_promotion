<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Api;

/**
 * Persistence for the promotion SKU list.
 */
interface PromotionProductRepositoryInterface
{
    /**
     * Configured rows ordered by position, then entity id.
     *
     * @return array<int, array{sku: string, position: int}>
     */
    public function getItems(): array;

    /**
     * Sets one stored SKU's position and leaves the other rows unchanged.
     *
     * @param string $sku
     * @param int $position
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function assignPosition(string $sku, int $position): void;

    /**
     * Configured SKUs ordered by position ascending.
     *
     * @return string[]
     */
    public function getOrderedSkus(): array;

    /**
     * Replace every row. Acquires the replace lock for this write only.
     *
     * Does not read the current list. Callers that build the next list from the
     * current rows must use modify() so the read and the write share one lock.
     *
     * @param string[] $skus
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function replaceAll(array $skus): void;

    /**
     * Read the latest SKUs under the replace lock, build the next list, and commit it.
     *
     * The lock is acquired once and released once. A nested replaceAll() on this
     * instance does not call GET_LOCK again. Lock failure throws before the read.
     *
     * @param callable $mutator
     * @return string[]
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function modify(callable $mutator): array;
}
