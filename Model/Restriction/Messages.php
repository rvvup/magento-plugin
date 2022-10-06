<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\Restriction;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Rvvup\Payments\Model\ConfigInterface as RvvupConfig;

class Messages
{
    /** @var ScopeConfigInterface */
    private $scopeConfig;

    private const RESTRICTIONS_CONFIG = RvvupConfig::RVVUP_CONFIG . 'product_restrictions/';
    private const PDP_MESSAGE = 'pdp_message';
    private const CHECKOUT_MESSAGE = 'checkout_message';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return string
     */
    public function getPdpMessage(): string
    {
        return (string) $this->scopeConfig->getValue(self::RESTRICTIONS_CONFIG . self::PDP_MESSAGE);
    }

    /**
     * @return string
     */
    public function getCheckoutMessage(): string
    {
        return (string) $this->scopeConfig->getValue(self::RESTRICTIONS_CONFIG . self::CHECKOUT_MESSAGE);
    }
}
