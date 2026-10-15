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

use Itc\Promotion\Api\PromotionManagementInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Itc_Promotion::manage';

    /**
     * Constructor.
     *
     * @param Context $context
     * @param PromotionManagementInterface $promotionManagement
     */
    public function __construct(
        Context $context,
        private readonly PromotionManagementInterface $promotionManagement
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $sku = trim((string) $this->getRequest()->getParam('sku'));
        try {
            $this->promotionManagement->removeProduct($sku);
            $this->messageManager->addSuccessMessage(__('The product has been removed from the promotion list.'));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
