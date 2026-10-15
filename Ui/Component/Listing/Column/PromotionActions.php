<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class PromotionActions extends Column
{
    /**
     * Constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @inheritdoc
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        foreach ($dataSource['data']['items'] as &$item) {
            $sku = (string) ($item['sku'] ?? '');
            if ($sku === '') {
                continue;
            }
            $item[$this->getData('name')] = [
                'delete' => [
                    'href' => $this->urlBuilder->getUrl('itc_promotion/promotion/delete', ['sku' => $sku]),
                    'label' => __('Delete'),
                    'post' => true,
                    'confirm' => [
                        'title' => __('Delete %1', $sku),
                        'message' => __('Remove this product from the promotion list?'),
                    ],
                ],
            ];
        }
        return $dataSource;
    }
}
