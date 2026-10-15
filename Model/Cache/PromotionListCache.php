<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Model\Cache;

use Magento\Framework\App\CacheInterface;

/**
 * Caches only the ordered SKU list. Prices and salability are always resolved live.
 */
class PromotionListCache
{
    public const CACHE_ID = 'itc_promotion_sku_list';
    public const CACHE_TAG = 'itc_promotion_list';
    private const LIFETIME = 86400;

    /**
     * Constructor.
     *
     * @param CacheInterface $cache
     */
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    /**
     * Load.
     *
     * @return string[]|null Null means the cache does not have a value.
     */
    public function load(): ?array
    {
        $raw = $this->cache->load(self::CACHE_ID);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }
        $skus = [];
        foreach ($decoded as $sku) {
            if (!is_string($sku)) {
                return null;
            }
            $skus[] = $sku;
        }
        return $skus;
    }

    /**
     * Save.
     *
     * @param string[] $skus
     */
    public function save(array $skus): void
    {
        $this->cache->save(
            (string) json_encode(array_values($skus)),
            self::CACHE_ID,
            [self::CACHE_TAG],
            self::LIFETIME
        );
    }

    /**
     * Invalidates the cached promotion SKU list.
     *
     * @return void
     */
    public function invalidate(): void
    {
        $this->cache->clean([self::CACHE_TAG]);
    }
}
