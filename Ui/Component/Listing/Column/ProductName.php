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

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ProductName extends Column
{
    /**
     * Constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param Escaper $escaper
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly Escaper $escaper,
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

        $field = (string) $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $label = (string) ($item[$field] ?? '');
            if ($productId <= 0) {
                $item[$field] = $this->escaper->escapeHtml($label);
                continue;
            }
            $url = $this->urlBuilder->getUrl('catalog/product/edit', ['id' => $productId]);
            $item[$field] = sprintf(
                '<a href="%s">%s</a>',
                $this->escaper->escapeUrl($url),
                $this->escaper->escapeHtml($label)
            );
        }

        return $dataSource;
    }
}
