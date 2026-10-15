<?php
/**
 * Itc Promotion Module
 *
 * @category    Itc
 * @package     Itc_Promotion
 * @author      Jason Zhuo
 */

declare(strict_types=1);

namespace Itc\Promotion\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;

class ModuleConfig
{
    public const XML_PATH_ENABLED = 'itc_promotion/general/enabled';

    /**
     * Constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * Global switch. Store-view overrides are not used.
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
    }
}
