<?php

declare(strict_types=1);

namespace Rvvup\Payments\ViewModel;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\ApiSettingsProvider;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\IsPaymentMethodAvailableInterface;
use Rvvup\Payments\Model\Logger;

class PayPal implements ArgumentInterface
{
    /**
     * @var \Rvvup\Payments\Model\Config
     */
    private $config;

    /**
     * @var \Rvvup\Payments\Model\IsPaymentMethodAvailableInterface
     */
    private $isPaymentMethodAvailable;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Psr\Log\LoggerInterface|Logger
     */
    private $logger;

    /** @var ApiSettingsProvider */
    private $apiSettingsProvider;

    /**
     * @param ConfigInterface $config
     * @param IsPaymentMethodAvailableInterface $isPaymentMethodAvailable
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface|Logger $logger
     * @param ApiSettingsProvider $apiSettingsProvider
     */
    public function __construct(
        ConfigInterface $config,
        IsPaymentMethodAvailableInterface $isPaymentMethodAvailable,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        ApiSettingsProvider $apiSettingsProvider
    ) {
        $this->config = $config;
        $this->isPaymentMethodAvailable = $isPaymentMethodAvailable;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->apiSettingsProvider = $apiSettingsProvider;
    }

    /**
     * Sets the flag if paypal is available to use and returns it.
     * It will always return either true/false.
     *
     * @param string $value
     * @return bool
     */
    public function isAvailable(string $value): bool
    {
        if (!$this->config->isActive()) {
            return false;
        }

        $storeCurrency = $this->getCurrentStoreCurrencyCode();

        if ($storeCurrency === null) {
            return false;
        }

        return $this->isPaymentMethodAvailable->execute('paypal', $value, $storeCurrency);
    }

    /**
     * Can use PayPal on PDP for the current Product's Type.
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
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
     * Each call should return a different ID, if no exception is thrown.
     * Hence, save init call result in templates to reuse for same container id.
     *
     * @return string
     */
    public function getButtonContainerId(): string
    {
        try {
            return sprintf('rvvup-paypal-express-button-%s', random_int(PHP_INT_MIN, PHP_INT_MAX));
        } catch (Exception $e) {
            /**
             * Exception only thrown if an appropriate source of randomness cannot be found.
             * https://www.php.net/manual/en/function.random-int.php
             */
            return 'rvvup-paypal-express-button';
        }
    }

    /**
     * @return string|null
     */
    private function getCurrentStoreCurrencyCode(): ?string
    {
        try {
            $currency = $this->storeManager->getStore()->getCurrentCurrency();

            return $currency === null ? null : $currency->getCode();
        } catch (Exception $ex) {
            $this->logger->error(
                'Exception thrown when fetching current store\'s currency with message: ' . $ex->getMessage()
            );

            return null;
        }
    }

    public function getPayLaterMessagingValue(string $path)
    {
        if (in_array($path, ['enabled', 'textSize'])) {
            return $this->apiSettingsProvider->getByPath('PAYPAL', "settings/product/payLaterMessaging/$path");
        }
        return $this->apiSettingsProvider->getByPath('PAYPAL', "settings/product/payLaterMessaging/$path/value");
    }
}
