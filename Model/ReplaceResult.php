<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Model;

use Itc\Promotion\Api\Data\ReplaceResultInterface;
use Magento\Framework\Api\AbstractSimpleObject;

class ReplaceResult extends AbstractSimpleObject implements ReplaceResultInterface
{
    /**
     * @inheritdoc
     */
    public function getSkus(): array
    {
        $skus = $this->_get('skus');
        return is_array($skus) ? $skus : [];
    }

    /**
     * @inheritdoc
     */
    public function setSkus(array $skus): ReplaceResultInterface
    {
        return $this->setData('skus', array_values($skus));
    }

    /**
     * @inheritdoc
     */
    public function getCount(): int
    {
        return (int) $this->_get('count');
    }

    /**
     * @inheritdoc
     */
    public function setCount(int $count): ReplaceResultInterface
    {
        return $this->setData('count', $count);
    }
}
