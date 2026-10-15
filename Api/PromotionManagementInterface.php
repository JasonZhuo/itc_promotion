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
 * Promotion list service shared by the public API, the integration API, and Admin.
 */
interface PromotionManagementInterface
{
    /**
     * Eligible storefront products in configured order. Empty when the module is disabled.
     *
     * @return \Itc\Promotion\Api\Data\StorefrontProductListInterface
     */
    public function getStorefrontProducts(): \Itc\Promotion\Api\Data\StorefrontProductListInterface;

    /**
     * Replace the entire promotion list. An empty array clears it.
     *
     * @param string[] $skus
     * @return \Itc\Promotion\Api\Data\ReplaceResultInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function replaceBySkus(array $skus): \Itc\Promotion\Api\Data\ReplaceResultInterface;

    /**
     * Add one catalog SKU to the shared list.
     *
     * @param string $sku
     * @param int|null $position Zero-based insert index. Null appends.
     * @return \Itc\Promotion\Api\Data\ReplaceResultInterface
     * @throws \Magento\Framework\Exception\InputException
     */
    public function addProduct(string $sku, ?int $position = null): \Itc\Promotion\Api\Data\ReplaceResultInterface;

    /**
     * Add catalog SKUs in one locked write. At most five SKUs, including those already stored.
     *
     * Positions use the same order as $skus. Null appends that SKU. Each insert sees the list
     * produced by the previous SKU in this request.
     *
     * @param string[] $skus
     * @param array $positions
     * @return \Itc\Promotion\Api\Data\ReplaceResultInterface
     * @throws \Magento\Framework\Exception\InputException
     */
    public function addProducts(array $skus, array $positions = []): \Itc\Promotion\Api\Data\ReplaceResultInterface;

    /**
     * Remove one SKU from the shared list.
     *
     * @param string $sku
     * @return \Itc\Promotion\Api\Data\ReplaceResultInterface
     * @throws \Magento\Framework\Exception\InputException
     */
    public function removeProduct(string $sku): \Itc\Promotion\Api\Data\ReplaceResultInterface;

    /**
     * Move an existing SKU to the given position. Other rows keep their positions.
     *
     * @param string $sku
     * @param int $position Whole number from 0 through 65535.
     * @return \Itc\Promotion\Api\Data\ReplaceResultInterface
     * @throws \Magento\Framework\Exception\InputException
     */
    public function moveProduct(string $sku, int $position): \Itc\Promotion\Api\Data\ReplaceResultInterface;

    /**
     * Replace one promotion SKU and set its position. One locked write, or no write.
     *
     * @param string $originalSku SKU currently stored on the row being edited.
     * @param string $sku Catalog SKU that should occupy that row.
     * @param int|null $position Zero-based index. Null keeps the current index.
     * @return \Itc\Promotion\Api\Data\ReplaceResultInterface
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function changeProduct(
        string $originalSku,
        string $sku,
        ?int $position = null
    ): \Itc\Promotion\Api\Data\ReplaceResultInterface;
}
