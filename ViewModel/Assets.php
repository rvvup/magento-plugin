<?php

declare(strict_types=1);

namespace Rvvup\Payments\ViewModel;

use Exception;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\PaymentMethodsAssetsGetInterface;
use Rvvup\Payments\Model\ConfigInterface;

class Assets implements ArgumentInterface
{
    /**
     * @var \Rvvup\Payments\Model\ConfigInterface
     */
    private $config;

    /**
     * @var \Rvvup\Payments\Api\PaymentMethodsAssetsGetInterface
     */
    private $paymentMethodsAssetsGet;

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
     * @param \Rvvup\Payments\Model\ConfigInterface $config
     * @param \Rvvup\Payments\Api\PaymentMethodsAssetsGetInterface $paymentMethodsAssetsGet
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Psr\Log\LoggerInterface|RvvupLog $logger
     * @return void
     */
    public function __construct(
        ConfigInterface $config,
        PaymentMethodsAssetsGetInterface $paymentMethodsAssetsGet,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->paymentMethodsAssetsGet = $paymentMethodsAssetsGet;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * Get the script assets only of all available payment methods for the current store currency.
     *
     * @return array
     */
    public function getPaymentMethodsScriptAssets(): array
    {
        $scripts = [];

        // If module not active, return empty array.
        if (!$this->config->isActive()) {
            return $scripts;
        }

        foreach ($this->getPaymentMethodsAssets() as $paymentMethod => $paymentMethodsAssets) {
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
     * Get the assets of all the available payment methods.
     *
     * @return array
     */
    private function getPaymentMethodsAssets(): array
    {
        // return empty array if we cannot get the store currency.
        if ($this->getStoreCurrency() === null) {
            return [];
        }

        return $this->paymentMethodsAssetsGet->execute('0', $this->getStoreCurrency());
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
}
