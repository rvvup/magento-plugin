<?php

declare(strict_types=1);

namespace Rvvup\Payments\Plugin;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Payment\Helper\Data;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\StoreManagerInterface;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\RvvupConfigProvider as Config;
use Rvvup\Payments\Model\SdkProxy;
use Rvvup\Payments\Traits\LoadMethods;

class LoadPaymentMethods
{
    use LoadMethods;

    /**
     * @var array
     */
    private $methods;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var SessionManagerInterface
     */
    private $checkoutSession;

    /**
     * @var SdkProxy
     */
    private $sdkProxy;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param SessionManagerInterface $checkoutSession
     * @param ConfigInterface $config
     * @param StoreManagerInterface $storeManager
     * @param SdkProxy $sdkProxy
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        SessionManagerInterface $checkoutSession,
        ConfigInterface $config,
        StoreManagerInterface $storeManager,
        SdkProxy $sdkProxy,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->config = $config;
        $this->sdkProxy = $sdkProxy;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->methods = [];
    }

    /**
     * @param Data $subject
     * @param array $result
     * @return array
     */
    public function afterGetPaymentMethods(Data $subject, array $result): array
    {
        try {
            /** @var \Magento\Quote\Api\Data\CartInterface $quote */
            $quote = $this->checkoutSession->getQuote();
        } catch (LocalizedException $ex) {
            // Silently handle exception.
            $quote = null;
        }

        if (isset($result['rvvup'])) {
            $items = $quote === null || $quote->getItems() === null ? [] : $quote->getItems();

            if (!$this->config->isActive()) {
                return $result;
            }
            if ($this->scopeConfig->getValue(Config::ALLOW_SPECIFIC_PATH)) {
                $country = $quote->getBillingAddress()->getCountryId();
                $countries = $this->scopeConfig->getValue(Config::SPECIFIC_COUNTRY_PATH);
                $availableCountries = explode(',', $countries);
                if (!in_array($country, $availableCountries)) {
                    return $result;
                }
            }

            $productTypes = $this->config->getValidProductTypes();

            foreach ($items as $item) {
                if (!in_array($item->getProductType(), $productTypes, true)) {
                    return $result;
                }
            }

            $this->template = $result['rvvup'];
            unset($result['rvvup']);
        }

        if (!$this->methods) {
            $currency = $this->storeManager->getStore()->getBaseCurrencyCode();

            if ($quote !== null && $quote->getQuoteCurrencyCode() !== null) {
                $currency = $quote->getQuoteCurrencyCode();
            }

            $methods = $this->sdkProxy->getMethods(
                $quote === null ? '0' : (string) $quote->getGrandTotal(),
                $currency
            );

            $country = $this->getCountryUsed($quote);
            if ($country && $country !== 'GB') {
                $this->removePaymentMethodByCode('YAPILY', $methods);
            }

            $this->methods = $methods;
        }

        return array_merge(
            $result,
            $this->processMethods($this->methods)
        );
    }

    /**
     * Get country used for checkout
     * @param CartInterface $quote
     * @return string|false
     */
    private function getCountryUsed(CartInterface $quote)
    {
        $address = $quote->getShippingAddress();
        if ($address && $address->getShippingMethod()) {
            if ($address->getShippingRateByCode($address->getShippingMethod())) {
                $addressId = $address->getShippingRateByCode($address->getShippingMethod())->getAddressId();
                return $quote->getAddressById($addressId)->getCountryId();
            }
        }
        return $quote->getBillingAddress() ? $quote->getBillingAddress()->getCountryId() : false;
    }

    /**
     * @param string $code
     * @param array $methods
     * @return void
     */
    private function removePaymentMethodByCode(string $code, array &$methods)
    {
        foreach ($methods as $key => $method) {
            if ($method['name'] === $code) {
                unset($methods[$key]);
                break;
            }
        }
    }
}
