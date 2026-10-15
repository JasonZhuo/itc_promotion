<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Test\Unit\Model\Eligibility;

use Itc\Promotion\Model\Eligibility\StorefrontEligibility;
use PHPUnit\Framework\TestCase;

class StorefrontEligibilityTest extends TestCase
{
    /**
     * Verifies order is kept and ineligible SKUs are skipped.
     *
     * @return void
     */
    public function testOrderIsKeptWhenProductsAreSkipped(): void
    {
        $eligibility = new StorefrontEligibility();
        $selected = $eligibility->select(
            ['disabled', 'other-site', 'unsalable', 'second', 'first'],
            [
                'disabled' => $this->row(false, true, [1]),
                'other-site' => $this->row(true, true, [2]),
                'unsalable' => $this->row(true, false, [1]),
                'second' => $this->row(true, true, [1]),
                'first' => $this->row(true, true, [1]),
            ],
            1
        );

        $this->assertSame(['second', 'first'], $selected);
    }

    /**
     * Verifies at most five eligible products are returned, in list order.
     *
     * @return void
     */
    public function testAtMostFiveEligibleProductsAreReturned(): void
    {
        $skus = ['a', 'b', 'c', 'd', 'e', 'f'];
        $catalog = [];
        foreach ($skus as $sku) {
            $catalog[$sku] = $this->row(true, true, [1]);
        }

        $selected = (new StorefrontEligibility())->select($skus, $catalog, 1);

        $this->assertSame(['a', 'b', 'c', 'd', 'e'], $selected);
    }

    /**
     * Builds one catalog eligibility row.
     *
     * @param bool $enabled
     * @param bool $salable
     * @param int[] $websiteIds
     * @return array{enabled: bool, salable: bool, website_ids: int[]}
     */
    private function row(bool $enabled, bool $salable, array $websiteIds): array
    {
        return [
            'enabled' => $enabled,
            'salable' => $salable,
            'website_ids' => $websiteIds,
        ];
    }
}
