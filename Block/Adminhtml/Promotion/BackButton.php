<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Block\Adminhtml\Promotion;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class BackButton implements ButtonProviderInterface
{
    /**
     * Constructor.
     *
     * @param UrlInterface $url
     */
    public function __construct(private readonly UrlInterface $url)
    {
    }

    /**
     * @inheritdoc
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Back'),
            'on_click' => sprintf("location.href = '%s';", $this->url->getUrl('*/*/')),
            'class' => 'back',
            'sort_order' => 10,
        ];
    }
}
