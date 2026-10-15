<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Model\Catalog;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\InputException;

/**
 * Resolves request SKUs to the canonical catalog_product_entity.sku value.
 */
class ExistingSkuChecker
{
    /**
     * Constructor.
     *
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /**
     * Returns catalog SKUs in request order.
     *
     * Matching is case-insensitive. The returned value is the stored catalog SKU.
     * An unknown SKU rejects the whole request.
     *
     * @param string[] $skus
     * @return string[]
     * @throws InputException
     */
    public function canonicalize(array $skus): array
    {
        if ($skus === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('catalog_product_entity');
        $folded = [];
        foreach ($skus as $sku) {
            $folded[$this->fold($sku)] = true;
        }
        $select = $connection->select()
            ->from($table, ['sku'])
            ->where('LOWER(sku) IN (?)', array_keys($folded));

        $byFold = [];
        foreach ($connection->fetchCol($select) as $sku) {
            $canonical = (string) $sku;
            $byFold[$this->fold($canonical)] = $canonical;
        }

        $resolved = [];
        $missing = [];
        foreach ($skus as $sku) {
            $fold = $this->fold($sku);
            if (!isset($byFold[$fold])) {
                $missing[] = $sku;
                continue;
            }
            $resolved[] = $byFold[$fold];
        }

        if ($missing !== []) {
            throw new InputException(
                __('These SKUs do not exist: %1', implode(', ', $missing))
            );
        }

        return $resolved;
    }

    /**
     * Folds a SKU for case-insensitive comparison.
     *
     * @param string $sku
     * @return string
     */
    private function fold(string $sku): string
    {
        return mb_strtolower($sku, 'UTF-8');
    }
}
