<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Test\Unit\Ui\Component\Listing\Column;

use Itc\Promotion\Ui\Component\Listing\Column\ProductName;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use PHPUnit\Framework\TestCase;

class ProductNameTest extends TestCase
{
    /**
     * Verifies the product name links to the admin catalog edit page.
     *
     * @return void
     */
    public function testNameLinksToTheCatalogEditPage(): void
    {
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')
            ->with('catalog/product/edit', ['id' => 15])
            ->willReturn('https://m248.com/admin/catalog/product/edit/id/15/');
        $column = $this->column($url);

        $result = $column->prepareDataSource([
            'data' => [
                'items' => [
                    ['product_id' => 15, 'name' => 'Joust Duffle Bag'],
                ],
            ],
        ]);

        $this->assertSame(
            '<a href="https://m248.com/admin/catalog/product/edit/id/15/">Joust Duffle Bag</a>',
            $result['data']['items'][0]['name']
        );
    }

    /**
     * Verifies a missing catalog product is not given an edit link.
     *
     * @return void
     */
    public function testMissingProductIdIsNotLinked(): void
    {
        $url = $this->createMock(UrlInterface::class);
        $url->expects($this->never())->method('getUrl');
        $column = $this->column($url);

        $result = $column->prepareDataSource([
            'data' => [
                'items' => [
                    ['product_id' => null, 'name' => 'Gone'],
                ],
            ],
        ]);

        $this->assertSame('Gone', $result['data']['items'][0]['name']);
    }

    /**
     * Builds the column with a real escaper double that returns the input.
     *
     * @param UrlInterface $url
     * @return ProductName
     */
    private function column(UrlInterface $url): ProductName
    {
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeUrl')->willReturnArgument(0);
        $escaper->method('escapeHtml')->willReturnArgument(0);
        $column = new ProductName(
            $this->createMock(ContextInterface::class),
            $this->createMock(UiComponentFactory::class),
            $url,
            $escaper
        );
        $column->setData('name', 'name');

        return $column;
    }
}
