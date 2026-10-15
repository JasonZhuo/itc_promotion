<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Model;

use Itc\Promotion\Api\Data\ReplaceResultInterface;
use Itc\Promotion\Api\Data\StorefrontProductListInterface;
use Itc\Promotion\Api\PromotionManagementInterface;
use Itc\Promotion\Api\PromotionProductRepositoryInterface;
use Itc\Promotion\Model\Cache\PromotionListCache;
use Itc\Promotion\Model\Catalog\ExistingSkuChecker;
use Itc\Promotion\Model\Config\ModuleConfig;
use Itc\Promotion\Model\Resolver\StorefrontProductResolver;
use Itc\Promotion\Model\Validator\SkuListValidator;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Webapi\Exception as WebapiException;

class PromotionManagement implements PromotionManagementInterface
{
    /**
     * Constructor.
     *
     * @param ModuleConfig $moduleConfig
     * @param SkuListValidator $skuListValidator
     * @param ExistingSkuChecker $existingSkuChecker
     * @param PromotionProductRepositoryInterface $repository
     * @param PromotionListCache $cache
     * @param StorefrontProductResolver $storefrontProductResolver
     * @param StorefrontProductListFactory $listFactory
     * @param ReplaceResultFactory $replaceResultFactory
     */
    public function __construct(
        private readonly ModuleConfig $moduleConfig,
        private readonly SkuListValidator $skuListValidator,
        private readonly ExistingSkuChecker $existingSkuChecker,
        private readonly PromotionProductRepositoryInterface $repository,
        private readonly PromotionListCache $cache,
        private readonly StorefrontProductResolver $storefrontProductResolver,
        private readonly StorefrontProductListFactory $listFactory,
        private readonly ReplaceResultFactory $replaceResultFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getStorefrontProducts(): StorefrontProductListInterface
    {
        if (!$this->moduleConfig->isEnabled()) {
            return $this->listFactory->create()->setItems([]);
        }

        $skus = $this->cache->load();
        if ($skus === null) {
            $skus = $this->repository->getOrderedSkus();
            $this->cache->save($skus);
        }

        return $this->listFactory->create()->setItems(
            $this->storefrontProductResolver->resolve($skus)
        );
    }

    /**
     * @inheritdoc
     */
    public function replaceBySkus(array $skus): ReplaceResultInterface
    {
        $this->assertEnabled();
        $canonical = $this->prepareReplacement($skus);
        $this->repository->replaceAll($canonical);
        $this->cache->invalidate();

        return $this->result($canonical);
    }

    /**
     * @inheritdoc
     */
    public function addProduct(string $sku, ?int $position = null): ReplaceResultInterface
    {
        return $this->addProducts([trim($sku)], [$position]);
    }

    /**
     * @inheritdoc
     */
    public function addProducts(array $skus, array $positions = []): ReplaceResultInterface
    {
        $this->assertEnabled();
        $requested = $this->skuListValidator->normalize($skus);
        if ($requested === []) {
            throw new InputException(__('Enter at least one SKU.'));
        }
        if ($positions === []) {
            $positions = array_fill(0, count($requested), null);
        }
        if (count($positions) !== count($requested)) {
            throw new InputException(
                __('Enter one position for each SKU, or leave positions empty.')
            );
        }

        $saved = $this->repository->modify(function (array $current) use ($requested, $positions): array {
            $canonical = $this->existingSkuChecker->canonicalize($requested);
            if (count($current) + count($canonical) > SkuListValidator::MAX_SKUS) {
                throw new InputException(
                    __('A promotion list cannot contain more than %1 SKUs.', SkuListValidator::MAX_SKUS)
                );
            }
            foreach ($canonical as $index => $sku) {
                if (in_array($sku, $current, true)) {
                    throw new InputException(__('SKU "%1" is already in the promotion list.', $sku));
                }
                $position = $positions[$index];
                if ($position === null || $position < 0 || $position > count($current)) {
                    $current[] = $sku;
                } else {
                    array_splice($current, $position, 0, [$sku]);
                }
            }
            return $current;
        });
        $this->cache->invalidate();

        return $this->result($saved);
    }

    /**
     * @inheritdoc
     */
    public function removeProduct(string $sku): ReplaceResultInterface
    {
        $this->assertEnabled();
        $saved = $this->repository->modify(function (array $current) use ($sku): array {
            $index = $this->findIndex($current, $sku);
            if ($index === false) {
                throw new InputException(__('SKU "%1" is not in the promotion list.', trim($sku)));
            }
            unset($current[$index]);
            return array_values($current);
        });
        $this->cache->invalidate();

        return $this->result($saved);
    }

    /**
     * @inheritdoc
     */
    public function moveProduct(string $sku, int $position): ReplaceResultInterface
    {
        $this->assertEnabled();
        if ($position < 0 || $position > 65535) {
            throw new InputException(__('Enter a position from 0 to 65535.'));
        }
        $this->repository->assignPosition(trim($sku), $position);
        $this->cache->invalidate();

        return $this->result(array_column($this->repository->getItems(), 'sku'));
    }

    /**
     * @inheritdoc
     */
    public function changeProduct(string $originalSku, string $sku, ?int $position = null): ReplaceResultInterface
    {
        $this->assertEnabled();
        $requested = $this->skuListValidator->normalize([trim($sku)])[0];
        $saved = $this->repository->modify(function (array $current) use ($originalSku, $requested, $position): array {
            $index = $this->findIndex($current, $originalSku);
            if ($index === false) {
                throw new InputException(__('SKU "%1" is not in the promotion list.', trim($originalSku)));
            }
            $stored = $current[$index];
            if ($this->fold($requested) === $this->fold($stored)) {
                $newSku = $stored;
            } else {
                $newSku = $this->existingSkuChecker->canonicalize([$requested])[0];
                if (in_array($newSku, $current, true)) {
                    throw new InputException(__('SKU "%1" is already in the promotion list.', $newSku));
                }
            }
            unset($current[$index]);
            $current = array_values($current);
            $target = $position === null ? $index : max(0, min($position, count($current)));
            array_splice($current, $target, 0, [$newSku]);
            return $current;
        });
        $this->cache->invalidate();

        return $this->result($saved);
    }

    /**
     * Validates the payload, then resolves canonical catalog SKUs.
     *
     * A second validation pass rejects two request values that fold to one catalog SKU.
     *
     * @param array<mixed> $skus
     * @return string[]
     * @throws InputException
     */
    private function prepareReplacement(array $skus): array
    {
        $normalized = $this->skuListValidator->normalize($skus);
        $canonical = $this->existingSkuChecker->canonicalize($normalized);
        return $this->skuListValidator->normalize($canonical);
    }

    /**
     * Finds a stored SKU without requiring the request to match catalog letter casing.
     *
     * @param string[] $skus
     * @param string $sku
     * @return int|false
     */
    private function findIndex(array $skus, string $sku): int|false
    {
        $needle = $this->fold(trim($sku));
        foreach ($skus as $index => $existing) {
            if ($this->fold($existing) === $needle) {
                return (int) $index;
            }
        }
        return false;
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

    /**
     * Builds the replace result.
     *
     * @param string[] $skus
     * @return ReplaceResultInterface
     */
    private function result(array $skus): ReplaceResultInterface
    {
        return $this->replaceResultFactory->create()
            ->setSkus($skus)
            ->setCount(count($skus));
    }

    /**
     * Asserts the module is enabled.
     *
     * @throws WebapiException
     */
    private function assertEnabled(): void
    {
        if (!$this->moduleConfig->isEnabled()) {
            throw new WebapiException(
                __('The promotion service is disabled.'),
                0,
                503
            );
        }
    }
}
