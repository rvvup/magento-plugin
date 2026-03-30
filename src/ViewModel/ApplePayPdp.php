<?php

declare(strict_types=1);

namespace Rvvup\Payments\ViewModel;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\PaymentMethodsSettingsGetInterface;
use Rvvup\Payments\Model\ConfigInterface;

/**
 * ViewModel for Apple Pay express checkout on product pages.
 */
class ApplePayPdp implements ArgumentInterface
{
    /** @var ConfigInterface */
    private $config;

    /** @var PaymentMethodsSettingsGetInterface */
    private $paymentMethodsSettingsGet;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var LoggerInterface */
    private $logger;

    /** @var array|null */
    private $settings;

    public function __construct(
        ConfigInterface $config,
        PaymentMethodsSettingsGetInterface $paymentMethodsSettingsGet,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->paymentMethodsSettingsGet = $paymentMethodsSettingsGet;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Check if Apple Pay express checkout is available on PDP.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        if (!$this->config->isActive()) {
            return false;
        }

        $settings = $this->getApplePaySettings();
        if ($settings === null) {
            return false;
        }

        $pdpExpressEnabled = $settings['pdp']['express']['enabled'] ?? false;

        return (bool) $pdpExpressEnabled;
    }

    /**
     * Check if the product type supports Apple Pay express checkout.
     *
     * @param ProductInterface $product
     * @return bool
     */
    public function canUseForProductType(ProductInterface $product): bool
    {
        switch ($product->getTypeId()) {
            case 'grouped':
            case 'bundle':
            case null:
                return false;
            default:
                return true;
        }
    }

    /**
     * @return array|null
     */
    private function getApplePaySettings(): ?array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        try {
            $storeCurrency = $this->storeManager->getStore()->getCurrentCurrency()->getCode();
            $allSettings = $this->paymentMethodsSettingsGet->execute('0', $storeCurrency);
            $this->settings = $allSettings['rvvup_apple_pay'] ?? null;
        } catch (Exception $e) {
            $this->logger->error('Error getting Apple Pay settings: ' . $e->getMessage());
            $this->settings = null;
        }

        return $this->settings;
    }
}
