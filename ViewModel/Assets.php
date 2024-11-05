<?php

declare(strict_types=1);

namespace Rvvup\Payments\ViewModel;

use Exception;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\PaymentMethodsAssetsGetInterface;
use Rvvup\Payments\Api\PaymentMethodsSettingsGetInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Config\RvvupConfigurationInterface;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Service\RvvupRestApi;

class Assets implements ArgumentInterface
{
    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * @var \Rvvup\Payments\Model\ConfigInterface
     */
    private $config;

    /**
     * @var \Rvvup\Payments\Api\PaymentMethodsAssetsGetInterface
     */
    private $paymentMethodsAssetsGet;

    /**
     * @var \Rvvup\Payments\Api\PaymentMethodsSettingsGetInterface
     */
    private $paymentMethodsSettingsGet;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * Set via di.xml
     *
     * @var \Psr\Log\LoggerInterface|RvvupLog
     */
    private $logger;

    /**
     * @var \Magento\Store\Api\Data\StoreInterface|null
     */
    private $store;

    /**
     * @var string|null
     */
    private $storeCurrency;

    /**
     * @var array|null
     */
    private $settings;

    /**
     * @var RvvupRestApi
     */
    private $rvvupApi;

    /**
     * @var RvvupConfigurationInterface
     */
    private $rvvupConfiguration;

    /**
     * @param SerializerInterface $serializer
     * @param ConfigInterface $config
     * @param PaymentMethodsAssetsGetInterface $paymentMethodsAssetsGet
     * @param PaymentMethodsSettingsGetInterface $paymentMethodsSettingsGet
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface|RvvupLog $logger
     * @param RvvupRestApi $rvvupApi
     * @param RvvupConfigurationInterface $rvvupConfiguration
     */
    public function __construct(
        SerializerInterface $serializer,
        ConfigInterface $config,
        PaymentMethodsAssetsGetInterface $paymentMethodsAssetsGet,
        PaymentMethodsSettingsGetInterface $paymentMethodsSettingsGet,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        RvvupRestApi                $rvvupApi,
        RvvupConfigurationInterface $rvvupConfiguration
    ) {
        $this->serializer = $serializer;
        $this->config = $config;
        $this->paymentMethodsAssetsGet = $paymentMethodsAssetsGet;
        $this->paymentMethodsSettingsGet = $paymentMethodsSettingsGet;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->rvvupApi = $rvvupApi;
        $this->rvvupConfiguration = $rvvupConfiguration;
    }

    /**
     * Get the script assets for the requested payment methods (all if none requested) for the current store currency.
     *
     * @param array|string[] $methodCodes
     * @return array
     */
    public function getPaymentMethodsScriptAssets(array $methodCodes = []): array
    {
        $scripts = [];

        // If module not active, return empty array.
        if (!$this->config->isActive()) {
            return $scripts;
        }

        foreach ($this->getPaymentMethodsAssets($methodCodes) as $paymentMethod => $paymentMethodsAssets) {
            $scripts[$paymentMethod] = [];

            foreach ($paymentMethodsAssets as $key => $asset) {
                if (!isset($asset['assetType']) || mb_strtolower($asset['assetType']) !== 'script') {
                    continue;
                }

                $scripts[$paymentMethod][$key] = $asset;
            }
        }

        return $scripts;
    }

    public function getPublishableKey(): string
    {
        return $this->rvvupConfiguration->getMerchantId((string)$this->getStore()->getId());
    }

    public function getCoreSdkUrl(): string
    {
        // TODO: this needs to be dynamic
        return "https://checkout.dev.rvvuptech.com/sdk/v1-unstable.js";
    }

    public function shouldLoadCoreSdk(): bool
    {
        //TODO: For now, only load on apple pay inline.
        return isset($this->getPaymentMethodsSettings()["rvvup_apple_pay"]);
    }

    /**
     * Get the serialized Rvvup Parameters object.
     *
     * @return string
     */
    public function getRvvupParametersJsObject(): string
    {
        $rvvupParameters = ['settings' => []];

        foreach ($this->getPaymentMethodsSettings() as $key => $methodSettings) {
            if (isset($methodSettings['assets'])) {
                unset($methodSettings['assets']);
            }

            $rvvupParameters['settings'][str_replace(Method::PAYMENT_TITLE_PREFIX, '', $key)] = $methodSettings;
        }

        if ($this->shouldLoadCoreSdk()) {
            try {

                $storeId = (string)$this->getStore()->getId();
                $checkoutInput = ["amount" => ["currency" => $this->getStoreCurrency() ?? "GBP", "amount" => 1]];
                try {
                    $checkoutInput["metadata"] = [
                        "domain" => $this->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB, true)
                    ];
                } catch (Exception $e) {
                    $this->logger->error('Ignoring error getting base url: ' . $e->getMessage());
                }

                $checkoutResult = $this->parsedCheckout($this->rvvupApi->createCheckout($storeId, $checkoutInput));
                if ($checkoutResult) {
                    $rvvupParameters['checkout'] = $checkoutResult;
                }
            } catch (Exception $e) {
                $this->logger->error('Ignoring error creating checkout: ' . $e->getMessage());
            }
        }
        return $this->serializer->serialize($rvvupParameters);
    }

    /**
     * Get the generated ID for a script element by its method and index key.
     *
     * @param string $method
     * @param string $index
     * @return string
     */
    public function getScriptElementId(string $method, string $index): string
    {
        return $method . '_script_' . $index;
    }

    /**
     * Get the URL param of the script element if set & string, otherwise empty string.
     *
     * @param array $scriptData
     * @return string
     */
    public function getScriptElementSrc(array $scriptData): string
    {
        return isset($scriptData['url']) && is_string($scriptData['url']) ? $scriptData['url'] : '';
    }

    /**
     * @param array $scriptData
     * @return array
     */
    public function getScriptDataAttributes(array $scriptData): array
    {
        return empty($scriptData['attributes']) || !is_array($scriptData['attributes'])
            ? []
            : $scriptData['attributes'];
    }

    /**
     * Get the settings of all payment methods if available.
     *
     * @return array
     */
    private function getPaymentMethodsSettings(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        // return empty array if we cannot get the store currency.
        if ($this->getStoreCurrency() === null) {
            return [];
        }

        $this->settings = $this->paymentMethodsSettingsGet->execute('0', $this->getStoreCurrency());

        return $this->settings;
    }

    /**
     * Get the assets of the requested (or all if none requested) payment methods if available.
     *
     * @param array|string[] $methodCodes
     * @return array
     */
    private function getPaymentMethodsAssets(array $methodCodes = []): array
    {
        // return empty array if we cannot get the store currency.
        if ($this->getStoreCurrency() === null) {
            return [];
        }

        return $this->paymentMethodsAssetsGet->execute('0', $this->getStoreCurrency(), $methodCodes);
    }

    /**
     * @return string
     */
    private function getStoreCurrency(): ?string
    {
        if ($this->storeCurrency !== null) {
            return $this->storeCurrency;
        }

        try {
            $store = $this->getStore();

            if ($store === null) {
                return $this->storeCurrency;
            }

            $this->storeCurrency = $store->getCurrentCurrency()->getCode();
        } catch (Exception $ex) {
            // Just log error.
            $this->logger->error(
                'Exception thrown when fetching current store\'s currency with message: ' . $ex->getMessage()
            );
        }

        return $this->storeCurrency;
    }

    /**
     * @return \Magento\Store\Api\Data\StoreInterface|null
     */
    private function getStore(): ?StoreInterface
    {
        if ($this->store !== null) {
            return $this->store;
        }

        try {
            $this->store = $this->storeManager->getStore();
        } catch (Exception $ex) {
            // Just log error.
            $this->logger->error('Exception thrown when fetching current store with message: ' . $ex->getMessage());
        }

        return $this->store;
    }

    private function parsedCheckout(array $checkoutResult): ?array
    {
        if (!isset($checkoutResult) || !isset($checkoutResult["url"]) || !isset($checkoutResult["id"])) {
            return null;
        }
        //TODO: Get from token field instead of URL
        $parsedUrl = parse_url($checkoutResult["url"]);
        parse_str($parsedUrl['query'], $queryParams);
        if (!$queryParams['token']) {
            return null;
        }
        return ["token" => $queryParams["token"], "id" => $checkoutResult["id"]];
    }
}
