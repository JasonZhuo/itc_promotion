<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Controller\Adminhtml\Promotion;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class NewAction extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Itc_Promotion::manage';

    /**
     * Constructor.
     *
     * @param Context $context
     * @param PageFactory $pageFactory
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Itc_Promotion::promotion');
        $page->getConfig()->getTitle()->prepend(__('Add Promotion Product'));
        return $page;
    }
}
