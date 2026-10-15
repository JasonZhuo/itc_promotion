<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Ui\Component\Form;

use Itc\Promotion\Api\PromotionProductRepositoryInterface;
use Itc\Promotion\Model\ResourceModel\PromotionItem\CollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class DataProvider extends AbstractDataProvider
{
    /**
     * Constructor.
     *
     * @param mixed $name
     * @param mixed $primaryFieldName
     * @param mixed $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param RequestInterface $request
     * @param PromotionProductRepositoryInterface $repository
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
        private readonly PromotionProductRepositoryInterface $repository,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * @inheritdoc
     */
    public function getData(): array
    {
        $sku = trim((string) $this->request->getParam('sku'));
        if ($sku === '') {
            return [];
        }
        $ordered = $this->repository->getOrderedSkus();
        $position = array_search($sku, $ordered, true);
        if ($position === false) {
            return [];
        }
        return [
            $sku => [
                'sku' => $sku,
                'original_sku' => $sku,
                'position' => $position,
            ],
        ];
    }
}
