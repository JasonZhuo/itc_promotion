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
 * One eligible storefront promotion product.
 */
interface StorefrontProductInterface
{
    /**
     * Returns the SKU.
     *
     * @return string
     */
    public function getSku(): string;

    /**
     * Sets the SKU.
     *
     * @param string $sku
     * @return $this
     */
    public function setSku(string $sku): self;

    /**
     * Returns the product ID.
     *
     * @return int
     */
    public function getProductId(): int;

    /**
     * Sets the product ID.
     *
     * @param int $productId
     * @return $this
     */
    public function setProductId(int $productId): self;

    /**
     * Returns the name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Sets the name.
     *
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self;

    /**
     * Store-scoped short description with HTML removed. Null when empty.
     *
     * @return string|null
     */
    public function getShortDescription(): ?string;

    /**
     * Sets the short description.
     *
     * @param string|null $shortDescription
     * @return $this
     */
    public function setShortDescription(?string $shortDescription): self;

    /**
     * Native regular catalog price for the current store.
     *
     * @return float
     */
    public function getPrice(): float;

    /**
     * Sets the price.
     *
     * @param float $price
     * @return $this
     */
    public function setPrice(float $price): self;

    /**
     * Configured special_price attribute, or null when it is not set.
     *
     * @return float|null
     */
    public function getSpecialPrice(): ?float;

    /**
     * Sets the special price.
     *
     * @param float|null $specialPrice
     * @return $this
     */
    public function setSpecialPrice(?float $specialPrice): self;

    /**
     * Native calculated catalog final price for the current store.
     *
     * @return float
     */
    public function getFinalPrice(): float;

    /**
     * Sets the final price.
     *
     * @param float $finalPrice
     * @return $this
     */
    public function setFinalPrice(float $finalPrice): self;

    /**
     * Returns the currency.
     *
     * @return string
     */
    public function getCurrency(): string;

    /**
     * Sets the currency.
     *
     * @param string $currency
     * @return $this
     */
    public function setCurrency(string $currency): self;

    /**
     * Image URL from Magento's image service, or null when it cannot be built.
     *
     * @return string|null
     */
    public function getImage(): ?string;

    /**
     * Sets the image.
     *
     * @param string|null $image
     * @return $this
     */
    public function setImage(?string $image): self;
}
