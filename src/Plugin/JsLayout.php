<?php declare(strict_types=1);

namespace Rvvup\Payments\Plugin;

use Magento\Checkout\Block\Checkout\LayoutProcessor;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\PaymentMethodsAvailableGetInterface;

class JsLayout
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Rvvup\Payments\Model\ConfigInterface
     */
    private $config;

    /**
     * @var \Rvvup\Payments\Model\PaymentMethodsAvailableGetInterface
     */
    private $paymentMethodsAvailableGet;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface|null
     */
    private $store;

    /**
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Rvvup\Payments\Model\ConfigInterface $config
     * @param \Rvvup\Payments\Model\PaymentMethodsAvailableGetInterface $paymentMethodsAvailableGet
     * @return void
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ConfigInterface $config,
        PaymentMethodsAvailableGetInterface $paymentMethodsAvailableGet
    ) {
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->paymentMethodsAvailableGet = $paymentMethodsAvailableGet;
    }

    /**
     * @param \Magento\Checkout\Block\Checkout\LayoutProcessor $subject
     * @param array $jsLayout
     * @return array
     */
    public function beforeProcess(LayoutProcessor $subject, $jsLayout): array
    {
        if ($this->config->isActive()) {
            // Add payment methods.
            $renders = &$jsLayout["components"]["checkout"]["children"]["steps"]["children"]["billing-step"]["children"]
            ["payment"]["children"]["renders"]["children"];

            $renders = array_merge($renders, $this->getRvvupMethods());
        }

        return [$jsLayout];
    }

    /**
     * @return array
     */
    private function getRvvupMethods(): array
    {
        $loadedMethods = $this->paymentMethodsAvailableGet->execute('0', $this->getCurrentStoreCurrencyCode());

        $template = ['component' => 'Rvvup_Payments/js/view/payment/rvvup'];
        $template['methods'] = [];
        foreach ($loadedMethods as $method) {
            $template['methods'][Method::PAYMENT_TITLE_PREFIX . $method['name']] = ['isBillingAddressRequired' => true];
        }
        return ['rvvup' => $template];
    }

    /**
     * @return string
     */
    private function getCurrentStoreCurrencyCode(): string
    {
        if ($this->getStore() === null) {
            return '';
        }

        try {
            $currency = $this->getStore()->getCurrentCurrency();

            return $currency === null ? '' : $currency->getCode();
        } catch (LocalizedException $ex) {
            // Silent return empty string.
            return '';
        }
    }

    /**
     * @return \Magento\Store\Model\StoreManagerInterface|null
     */
    private function getStore(): ?StoreInterface
    {
        if ($this->store !== null) {
            return $this->store;
        }

        try {
            $this->store = $this->storeManager->getStore();

            return $this->store;
        } catch (NoSuchEntityException $ex) {
            // Silent fail, return null.
            return $this->store;
        }
    }
}
