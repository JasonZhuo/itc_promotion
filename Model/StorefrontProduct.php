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

use Itc\Promotion\Api\Data\StorefrontProductInterface;
use Magento\Framework\Api\AbstractSimpleObject;

class StorefrontProduct extends AbstractSimpleObject implements StorefrontProductInterface
{
    /**
     * @inheritdoc
     */
    public function getSku(): string
    {
        return (string) $this->_get(self::KEY_SKU);
    }

    /**
     * @inheritdoc
     */
    public function setSku(string $sku): StorefrontProductInterface
    {
        return $this->setData(self::KEY_SKU, $sku);
    }

    /**
     * @inheritdoc
     */
    public function getProductId(): int
    {
        return (int) $this->_get(self::KEY_PRODUCT_ID);
    }

    /**
     * @inheritdoc
     */
    public function setProductId(int $productId): StorefrontProductInterface
    {
        return $this->setData(self::KEY_PRODUCT_ID, $productId);
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return (string) $this->_get(self::KEY_NAME);
    }

    /**
     * @inheritdoc
     */
    public function setName(string $name): StorefrontProductInterface
    {
        return $this->setData(self::KEY_NAME, $name);
    }

    /**
     * @inheritdoc
     */
    public function getShortDescription(): ?string
    {
        $value = $this->_get(self::KEY_SHORT_DESCRIPTION);
        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritdoc
     */
    public function setShortDescription(?string $shortDescription): StorefrontProductInterface
    {
        return $this->setData(self::KEY_SHORT_DESCRIPTION, $shortDescription);
    }

    /**
     * @inheritdoc
     */
    public function getPrice(): float
    {
        return (float) $this->_get(self::KEY_PRICE);
    }

    /**
     * @inheritdoc
     */
    public function setPrice(float $price): StorefrontProductInterface
    {
        return $this->setData(self::KEY_PRICE, $price);
    }

    /**
     * @inheritdoc
     */
    public function getSpecialPrice(): ?float
    {
        $value = $this->_get(self::KEY_SPECIAL_PRICE);
        return $value === null ? null : (float) $value;
    }

    /**
     * @inheritdoc
     */
    public function setSpecialPrice(?float $specialPrice): StorefrontProductInterface
    {
        return $this->setData(self::KEY_SPECIAL_PRICE, $specialPrice);
    }

    /**
     * @inheritdoc
     */
    public function getFinalPrice(): float
    {
        return (float) $this->_get(self::KEY_FINAL_PRICE);
    }

    /**
     * @inheritdoc
     */
    public function setFinalPrice(float $finalPrice): StorefrontProductInterface
    {
        return $this->setData(self::KEY_FINAL_PRICE, $finalPrice);
    }

    /**
     * @inheritdoc
     */
    public function getCurrency(): string
    {
        return (string) $this->_get(self::KEY_CURRENCY);
    }

    /**
     * @inheritdoc
     */
    public function setCurrency(string $currency): StorefrontProductInterface
    {
        return $this->setData(self::KEY_CURRENCY, $currency);
    }

    /**
     * @inheritdoc
     */
    public function getImage(): ?string
    {
        $value = $this->_get(self::KEY_IMAGE);
        return $value === null ? null : (string) $value;
    }

    /**
     * @inheritdoc
     */
    public function setImage(?string $image): StorefrontProductInterface
    {
        return $this->setData(self::KEY_IMAGE, $image);
    }

    private const KEY_SKU = 'sku';
    private const KEY_PRODUCT_ID = 'product_id';
    private const KEY_NAME = 'name';
    private const KEY_SHORT_DESCRIPTION = 'short_description';
    private const KEY_PRICE = 'price';
    private const KEY_SPECIAL_PRICE = 'special_price';
    private const KEY_FINAL_PRICE = 'final_price';
    private const KEY_CURRENCY = 'currency';
    private const KEY_IMAGE = 'image';
}
