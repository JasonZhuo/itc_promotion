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
use Itc\Promotion\Model\Validator\SkuListValidator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action implements HttpPostActionInterface
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
        $data = $this->formData();
        $sku = trim((string) ($data['sku'] ?? ''));
        $originalSku = trim((string) ($data['original_sku'] ?? ''));
        $positionRaw = $data['position'] ?? null;

        try {
            if ($originalSku !== '') {
                if (str_contains($sku, ',')) {
                    throw new InputException(__('Edit one SKU at a time.'));
                }
                $this->promotionManagement->changeProduct(
                    $originalSku,
                    $sku,
                    $this->singlePosition($positionRaw)
                );
                $this->messageManager->addSuccessMessage(__('The promotion product has been saved.'));
            } else {
                $skus = $this->splitSkus($sku);
                $this->promotionManagement->addProducts(
                    $skus,
                    $this->splitPositions($positionRaw, count($skus))
                );
                $this->messageManager->addSuccessMessage(__('The products have been added to the promotion list.'));
            }
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            $redirect = $this->resultRedirectFactory->create();
            if ($originalSku !== '') {
                return $redirect->setPath('*/*/edit', ['sku' => $originalSku]);
            }
            return $redirect->setPath('*/*/new');
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }

    /**
     * Reads the UI form payload.
     *
     * Magento posts the provider data at the top level. A nested data array is also accepted.
     *
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        $posted = $this->getRequest()->getPostValue();
        if (!is_array($posted)) {
            $posted = [];
        }
        $nested = $this->getRequest()->getParam('data');
        $data = is_array($nested) && $nested !== [] ? $nested : $posted;
        if (isset($data['general']) && is_array($data['general'])) {
            $data = array_merge($data, $data['general']);
        }

        return $data;
    }

    /**
     * Splits a comma-separated SKU field.
     *
     * @param string $value
     * @return string[]
     * @throws InputException
     */
    private function splitSkus(string $value): array
    {
        if ($value === '') {
            throw new InputException(__('Enter at least one SKU.'));
        }
        $skus = array_map('trim', explode(',', $value));
        foreach ($skus as $sku) {
            if ($sku === '') {
                throw new InputException(__('The SKU list contains an empty value.'));
            }
        }
        if (count($skus) > SkuListValidator::MAX_SKUS) {
            throw new InputException(
                __('Add at most %1 SKUs at a time.', SkuListValidator::MAX_SKUS)
            );
        }

        return $skus;
    }

    /**
     * Splits positions so each SKU has one index. An empty field appends every SKU.
     *
     * @param mixed $value
     * @param int $skuCount
     * @return array<int, int|null>
     * @throws InputException
     */
    private function splitPositions(mixed $value, int $skuCount): array
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return array_fill(0, $skuCount, null);
        }
        $parts = array_map('trim', explode(',', $raw));
        if (count($parts) !== $skuCount) {
            throw new InputException(
                __('Enter one position for each SKU, separated by commas, or leave Position empty.')
            );
        }
        $positions = [];
        foreach ($parts as $part) {
            if ($part === '') {
                $positions[] = null;
                continue;
            }
            if (preg_match('/^\d+$/', $part) !== 1) {
                throw new InputException(__('Position "%1" must be a whole number.', $part));
            }
            $positions[] = (int) $part;
        }

        return $positions;
    }

    /**
     * Reads one edit position. Empty keeps the current index.
     *
     * @param mixed $value
     * @return int|null
     * @throws InputException
     */
    private function singlePosition(mixed $value): ?int
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d+$/', $raw) !== 1) {
            throw new InputException(__('Position "%1" must be a whole number.', $raw));
        }

        return (int) $raw;
    }
}
