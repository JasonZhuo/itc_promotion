<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Model\Resolver;

use Itc\Promotion\Api\Data\StorefrontProductInterface;
use Itc\Promotion\Model\Eligibility\StorefrontEligibility;
use Itc\Promotion\Model\Media\ProductImageUrl;
use Itc\Promotion\Model\Pricing\ProductPriceReader;
use Itc\Promotion\Model\StorefrontProductFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Customer\Model\Group as CustomerGroup;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Area;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds the public payload from the current store, website, MSI salability, and native prices.
 */
class StorefrontProductResolver
{
    /**
     * Constructor.
     *
     * @param CollectionFactory $productCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param StockResolverInterface $stockResolver
     * @param AreProductsSalableInterface $areProductsSalable
     * @param StorefrontEligibility $eligibility
     * @param ProductPriceReader $priceReader
     * @param StorefrontProductFactory $productFactory
     * @param Emulation $emulation
     * @param ProductImageUrl $productImageUrl
     * @param CustomerSession $customerSession
     */
    public function __construct(
        private readonly CollectionFactory $productCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly StockResolverInterface $stockResolver,
        private readonly AreProductsSalableInterface $areProductsSalable,
        private readonly StorefrontEligibility $eligibility,
        private readonly ProductPriceReader $priceReader,
        private readonly StorefrontProductFactory $productFactory,
        private readonly Emulation $emulation,
        private readonly ProductImageUrl $productImageUrl,
        private readonly CustomerSession $customerSession
    ) {
    }

    /**
     * Resolves storefront promotion products for the current store.
     *
     * @param string[] $orderedSkus
     * @return StorefrontProductInterface[]
     */
    public function resolve(array $orderedSkus): array
    {
        if ($orderedSkus === []) {
            return [];
        }
        //get the store and website id, need to consider the multi-store and multi-website configuration
        $store = $this->storeManager->getStore();
        $storeId = (int) $store->getId();
        $websiteId = (int) $store->getWebsiteId();
        $products = $this->loadProducts($orderedSkus, $storeId, $websiteId);
        $salable = $this->loadSalability($orderedSkus, (string) $store->getWebsite()->getCode());

        $catalog = [];
        foreach ($orderedSkus as $sku) {
            $product = $products[$sku] ?? null;
            if (!$product instanceof Product) {
                continue;
            }
            $catalog[$sku] = [
                'enabled' => (int) $product->getStatus() === Status::STATUS_ENABLED,
                'website_ids' => array_map('intval', $product->getWebsiteIds()),
                'salable' => !empty($salable[$sku]),
            ];
        }

        $selected = $this->eligibility->select($orderedSkus, $catalog, $websiteId);
        return $this->mapProducts($selected, $products, $storeId);
    }

    /**
     * Loads catalog products for the given SKUs and store.
     *
     * @param string[] $skus
     * @param int $storeId
     * @param int $websiteId
     * @return array<string, Product>
     */
    private function loadProducts(array $skus, int $storeId, int $websiteId): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId);
        //get the product attributes by request, not all the product attributes
        $collection->addAttributeToSelect([
            'name',
            'short_description',
            'price',
            'special_price',
            'image',
            'small_image',
            'thumbnail',
            'status',
        ]);
        $collection->addAttributeToFilter('sku', ['in' => $skus]);
        $collection->addPriceData(CustomerGroup::NOT_LOGGED_IN_ID, $websiteId);
        $collection->addWebsiteNamesToResult();

        $bySku = [];
        foreach ($collection as $product) {
            if ($product instanceof Product) {
                $bySku[(string) $product->getSku()] = $product;
            }
        }
        return $bySku;
    }

    /**
     * Loads MSI salability for the website stock.
     *
     * @param string[] $skus
     * @param string $websiteCode
     * @return array<string, bool>
     */
    private function loadSalability(array $skus, string $websiteCode): array
    {
        $stock = $this->stockResolver->execute(SalesChannelInterface::TYPE_WEBSITE, $websiteCode);
        $results = $this->areProductsSalable->execute($skus, (int) $stock->getStockId());
        $map = [];
        foreach ($results as $result) {
            $map[$result->getSku()] = $result->isSalable();
        }
        return $map;
    }

    /**
     * Maps eligible products to the storefront payload.
     *
     * Products are keyed by SKU.
     *
     * @param string[] $selected
     * @param array $products
     * @param int $storeId
     * @return StorefrontProductInterface[]
     */
    private function mapProducts(array $selected, array $products, int $storeId): array
    {
        $items = [];
        $previousGroupId = (int) $this->customerSession->getCustomerGroupId();
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        $this->customerSession->setCustomerGroupId(CustomerGroup::NOT_LOGGED_IN_ID);
        try {
            $currency = (string) $this->storeManager->getStore()->getCurrentCurrencyCode();
            foreach ($selected as $sku) {
                $product = $products[$sku];
                $items[] = $this->productFactory->create()
                    ->setSku($sku)
                    ->setProductId((int) $product->getId())
                    ->setName((string) $product->getName())
                    ->setShortDescription($this->shortDescription($product))
                    ->setPrice($this->priceReader->getRegularPrice($product))
                    ->setSpecialPrice($this->priceReader->getSpecialPrice($product))
                    ->setFinalPrice($this->priceReader->getFinalPrice($product))
                    ->setCurrency($currency)
                    ->setImage($this->productImageUrl->get($product));
            }
        } finally {
            $this->emulation->stopEnvironmentEmulation();
            $this->customerSession->setCustomerGroupId($previousGroupId);
        }
        return $items;
    }

    /**
     * Returns plain text for the product short description.
     *
     * @param Product $product
     * @return ?string
     */
    private function shortDescription(Product $product): ?string
    {
        $raw = $product->getData('short_description');
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        //if the short description is WYSIWYG, need to strip the tags
        $text = trim(strip_tags($raw));
        return $text === '' ? null : $text;
    }
}
