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
use Itc\Promotion\Api\PromotionProductRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

class Move extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Itc_Promotion::manage';

    /**
     * Constructor.
     *
     * @param Context $context
     * @param PromotionManagementInterface $promotionManagement
     * @param PromotionProductRepositoryInterface $promotionItems
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        private readonly PromotionManagementInterface $promotionManagement,
        private readonly PromotionProductRepositoryInterface $promotionItems,
        private readonly JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $sku = trim((string) $this->getRequest()->getParam('sku'));
        $position = trim((string) $this->getRequest()->getParam('position'));
        $result = $this->resultJsonFactory->create();

        try {
            if ($sku === '' || preg_match('/^\d+$/', $position) !== 1) {
                throw new LocalizedException(__('Enter a whole-number position.'));
            }
            $this->promotionManagement->moveProduct($sku, (int) $position);

            return $result->setData([
                'success' => true,
                'message' => (string) __('Position updated.'),
                'items' => $this->promotionItems->getItems(),
            ]);
        } catch (LocalizedException $exception) {
            return $result->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
