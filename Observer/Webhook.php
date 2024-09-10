<?php

declare(strict_types=1);

namespace Rvvup\Payments\Observer;

use GuzzleHttp\Client;
use Magento\Config\Model\Config\Backend\Admin\Custom;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Rvvup\Payments\Model\Config\RvvupConfigurationInterface;
use Rvvup\Payments\Model\UserAgentBuilder;
use Rvvup\Sdk\GraphQlSdkFactory;

class Webhook implements ObserverInterface
{
    /** @var string */
    private const WEB = 'web';

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var RvvupConfigurationInterface */
    private $config;

    /** @var GraphQlSdkFactory */
    private $sdkFactory;

    /** @var UserAgentBuilder */
    private $userAgentBuilder;

    /** @var string */
    private $merchantId;

    /** @var string */
    private $endpoint;

    /**
     * @param StoreManagerInterface $storeManager
     * @param RvvupConfigurationInterface $config
     * @param ScopeConfigInterface $scopeConfig
     * @param GraphQlSdkFactory $sdkFactory
     * @param UserAgentBuilder $userAgentBuilder
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        RvvupConfigurationInterface $config,
        ScopeConfigInterface $scopeConfig,
        GraphQlSdkFactory $sdkFactory,
        UserAgentBuilder $userAgentBuilder
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->config = $config;
        $this->sdkFactory = $sdkFactory;
        $this->userAgentBuilder = $userAgentBuilder;
    }

    /**
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $event = $observer->getEvent();
        $configData = $event->getData('configData');
        $storeId = $event->getRequest()->getParam('store');
        $websiteId = $event->getRequest()->getParam('website');
        if (isset($configData)) {
            $section = $configData['section'];
            if ($section == self::WEB || $section == Custom::XML_PATH_PAYMENT) {
                if (!$websiteId && !$storeId) {
                    $this->updateStores($this->storeManager->getStores());
                } elseif ($websiteId) {
                    $website = $this->storeManager->getWebsite($websiteId);
                    $this->updateStores($website->getStores());
                } elseif ($storeId) {
                    $store = $this->storeManager->getStore($storeId);
                    $url = $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, true) . 'rvvup/webhook';
                    $this->registerWebhookUrl($url, $storeId);
                }
            }
        }
    }

    /**
     * @param array $stores
     * @return void
     */
    private function updateStores(array $stores): void
    {
        foreach ($stores as $store) {
            $url = $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, true) . 'rvvup/webhook';
            $this->registerWebhookUrl($url, $store->getId());
        }
    }

    /**
     * Register rvvup webhook url
     * @param string $url
     * @param int $storeId
     * @return void
     * @throws NoSuchEntityException
     */
    private function registerWebhookUrl(string $url, int $storeId): void
    {
        $merchantId = $this->config->getMerchantId($storeId);
        $endpoint = $this->config->getGraphQlUrl($storeId);
        if ($this->merchantId !== $merchantId && $this->endpoint !== $endpoint) {
            $connection = $this->sdkFactory->create([
                'endpoint' => $endpoint,
                'merchantId' => $merchantId,
                'authToken' => $this->config->getBasicAuthToken($storeId),
                'userAgent' => $this->userAgentBuilder->get(),
                'debug' => false,
                'adapter' => (new Client()),
            ]);
            $connection->registerWebhook($url);
            $this->merchantId = $merchantId;
            $this->endpoint = $endpoint;
        }
    }
}
