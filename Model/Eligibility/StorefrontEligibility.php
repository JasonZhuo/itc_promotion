<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Model\Eligibility;

/**
 * Picks eligible SKUs in promotion order, then applies the response limit.
 */
class StorefrontEligibility
{
    public const MAX_RESULTS = 5;

    /**
     * Selects eligible SKUs in promotion order.
     *
     * Catalog rows are keyed by SKU and contain enabled, website_ids, and salable.
     *
     * @param string[] $orderedSkus
     * @param array $catalog
     * @param int $websiteId
     * @return string[]
     */
    public function select(array $orderedSkus, array $catalog, int $websiteId): array
    {
        $selected = [];
        foreach ($orderedSkus as $sku) {
            $row = $catalog[$sku] ?? null;
            if ($row === null || !$row['enabled'] || !$row['salable']) {
                continue;
            }
            if (!in_array($websiteId, $row['website_ids'], true)) {
                continue;
            }
            $selected[] = $sku;
            if (count($selected) >= self::MAX_RESULTS) {
                break;
            }
        }
        return $selected;
    }
}
