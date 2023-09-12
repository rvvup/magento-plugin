<?php

declare(strict_types=1);

namespace Rvvup\Payments\ViewModel;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\ApiSettingsProvider;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\IsPaymentMethodAvailableInterface;

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
     * @var \Psr\Log\LoggerInterface|RvvupLog
     */
    private $logger;

    /** @var ApiSettingsProvider */
    private $apiSettingsProvider;

    /**
     * @var Session
     */
    private $session;

    /**
     * @param ConfigInterface $config
     * @param IsPaymentMethodAvailableInterface $isPaymentMethodAvailable
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface|RvvupLog $logger
     * @param ApiSettingsProvider $apiSettingsProvider
     * @param Session $session
     */
    public function __construct(
        ConfigInterface $config,
        IsPaymentMethodAvailableInterface $isPaymentMethodAvailable,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        ApiSettingsProvider $apiSettingsProvider,
        Session $session
    ) {
        $this->config = $config;
        $this->isPaymentMethodAvailable = $isPaymentMethodAvailable;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->apiSettingsProvider = $apiSettingsProvider;
        $this->session = $session;
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

    /**
     * @return float
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getAmount(): float
    {
        $quote = $this->session->getQuote();
        if ($quote) {
            return (float)$this->session->getQuote()->getBaseGrandTotal();
        }
        return 0;
    }

    /**
     * @return string
     */
    public function getHtmlContainers()
    {
        return '<div class="paypal-messaging" id="credit"></div> <div class="paypal-messaging" id="pay-in-three"></div>';
    }

    /**
     * @param string $amount
     * @return string
     */
    public function getMessagingScripts(string $amount): string
    {
        return $this->getCreditScript($amount) . $this->getPayInThreeScript($amount);
    }

    /**
     * @param string $amount
     * @return string
     */
    public function getCreditScript(string $amount): string
    {
        $creditUrl = 'https://www.paypal.com/sdk/js?client-id=AYeow1IuiTABhP_c_hT7MAlLg4dp2b2pk2vPEc6mivf7CLhFWDIW3OlXLw52eFqbYwFkv5oRJIm-ZSke&merchant-id=HPPKWZK7HQXHC&components=messages';
        $creditScript = "<script src=$creditUrl data-namespace='credit'></script>";
        $js = "<script>
        credit.Messages({
            amount: $amount,
            channel: 'Upstream'
        }).render('#credit');
        </script>";

        return $creditScript . $js;
    }

    /**
     * @param string $amount
     * @return string
     */
    private function getPayInThreeScript(string $amount): string
    {
        $payInThreeUrl = 'https://www.paypal.com/sdk/js?client-id=AVugn8jhq9GAoM2IYkZ-GxKtscZSh7E1J-aTLw-vgVROngG5Ftv8-e02WtIpB5t_9AAWcqZbogJjTdIA&merchant-id=HPPKWZK7HQXHC&components=messages';

        $payInThreeScript = "<script src=$payInThreeUrl data-namespace='pay'></script>";
        $js = "<script>
        pay.Messages({
            amount: $amount,
            channel: 'Upstream'
        }).render('#pay-in-three');
        </script>";

        return $payInThreeScript . $js;
    }
}
