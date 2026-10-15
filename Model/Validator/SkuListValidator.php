<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Model\Validator;

use Magento\Framework\Exception\InputException;

/**
 * Validates a replacement SKU list before any database write.
 */
class SkuListValidator
{
    public const MAX_SKUS = 5;
    public const MAX_LENGTH = 64;

    /**
     * Normalize.
     *
     * @param array<mixed> $skus
     * @return string[]
     * @throws InputException
     */
    public function normalize(array $skus): array
    {
        if (count($skus) > self::MAX_SKUS) {
            throw new InputException(
                __('A promotion list cannot contain more than %1 SKUs.', self::MAX_SKUS)
            );
        }

        $normalized = [];
        $seen = [];
        foreach ($skus as $index => $sku) {
            if (!is_string($sku)) {
                throw new InputException(
                    __('SKU at position %1 is malformed. Each SKU must be a string.', $index)
                );
            }
            $sku = trim($sku);
            if ($sku === '' || strlen($sku) > self::MAX_LENGTH || $this->hasControlCharacter($sku)) {
                throw new InputException(
                    __('SKU at position %1 is malformed.', $index)
                );
            }
            if (isset($seen[$sku])) {
                throw new InputException(
                    __('Duplicate SKU "%1" is not allowed.', $sku)
                );
            }
            $seen[$sku] = true;
            $normalized[] = $sku;
        }

        return $normalized;
    }

    /**
     * Returns whether has control character.
     *
     * @param string $sku
     * @return bool
     */
    private function hasControlCharacter(string $sku): bool
    {
        return preg_match('/[\x00-\x1F\x7F]/', $sku) === 1;
    }
}
