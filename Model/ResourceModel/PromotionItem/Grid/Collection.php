<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Model\ResourceModel\PromotionItem\Grid;

use Itc\Promotion\Model\ResourceModel\PromotionItem as PromotionItemResource;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;

class Collection extends SearchResult
{
    /**
     * Constructor.
     *
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param EavConfig $eavConfig
     * @param mixed $mainTable
     * @param mixed $resourceModel
     * @param mixed $identifierName
     * @param mixed $connectionName
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        private readonly EavConfig $eavConfig,
        $mainTable = 'itc_promotion_item',
        $resourceModel = PromotionItemResource::class,
        $identifierName = 'entity_id',
        $connectionName = null
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel,
            $identifierName,
            $connectionName
        );
    }

    /**
     * @inheritdoc
     */
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->joinCatalogProduct();
        $this->joinProductAttribute('name');
        $this->joinProductAttribute('price');
        $this->joinProductAttribute('special_price');
        $this->joinProductAttribute('status');
        $this->joinStockStatus();
        $this->mapGridFields();
        $this->setOrder('position', self::SORT_ORDER_ASC);

        return $this;
    }

    /**
     * Resolves the catalog product by SKU, then exposes its entity id.
     *
     * @return void
     */
    private function joinCatalogProduct(): void
    {
        $this->getSelect()->joinLeft(
            ['catalog_product' => $this->getTable('catalog_product_entity')],
            'catalog_product.sku = main_table.sku',
            ['product_id' => 'catalog_product.entity_id']
        );
    }

    /**
     * Joins one default-scope product attribute by the catalog product id.
     *
     * @param string $code
     * @return void
     */
    private function joinProductAttribute(string $code): void
    {
        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $code);
        $alias = $code . '_attr';
        $this->getSelect()->joinLeft(
            [$alias => $attribute->getBackendTable()],
            sprintf(
                '%1$s.entity_id = catalog_product.entity_id'
                . ' AND %1$s.attribute_id = %2$d AND %1$s.store_id = %3$d',
                $alias,
                (int) $attribute->getAttributeId(),
                Store::DEFAULT_STORE_ID
            ),
            [$code => $alias . '.value']
        );
        $this->addFilterToMap($code, $alias . '.value');
    }

    /**
     * Joins the default-website stock status by product id.
     *
     * @return void
     */
    private function joinStockStatus(): void
    {
        $this->getSelect()->joinLeft(
            ['catalog_stock' => $this->getTable('cataloginventory_stock_status')],
            'catalog_stock.product_id = catalog_product.entity_id'
            . ' AND catalog_stock.website_id = 0 AND catalog_stock.stock_id = 1',
            ['stock_status' => 'catalog_stock.stock_status']
        );
        $this->addFilterToMap('stock_status', 'catalog_stock.stock_status');
    }

    /**
     * Keeps filters and sorting on the promotion row when names overlap a join.
     *
     * @return void
     */
    private function mapGridFields(): void
    {
        $this->addFilterToMap('entity_id', 'main_table.entity_id');
        $this->addFilterToMap('sku', 'main_table.sku');
        $this->addFilterToMap('position', 'main_table.position');
        $this->addFilterToMap('updated_at', 'main_table.updated_at');
        $this->addFilterToMap('product_id', 'catalog_product.entity_id');
    }
}
