<?php
declare(strict_types=1);

namespace Rvvup\Payments\Model\Queue;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Rvvup\Payments\Model\Config\RvvupConfigurationInterface;
use Rvvup\Payments\Model\SdkProxy;
use Rvvup\Payments\Service\ApiProvider;
use Rvvup\Payments\Plugin\LoadMethodInstances;

class QueueContextCleaner
{

    /** @var RvvupConfigurationInterface */
    private $rvvupConfiguration;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var SdkProxy */
    private $sdkProxy;

    /** @var ApiProvider */
    private $apiProvider;

    /** @var LoadMethodInstances */
    private $loadMethodInstances;

    /**
     * @param RvvupConfigurationInterface $rvvupConfiguration
     * @param ScopeConfigInterface $scopeConfig
     * @param SdkProxy $sdkProxy
     * @param ApiProvider $apiProvider
     * @param LoadMethodInstances $loadMethodInstances
     */
    public function __construct(
        RvvupConfigurationInterface $rvvupConfiguration,
        ScopeConfigInterface        $scopeConfig,
        SdkProxy    $sdkProxy,
        ApiProvider $apiProvider,
        LoadMethodInstances $loadMethodInstances
    ) {
        $this->rvvupConfiguration = $rvvupConfiguration;
        $this->scopeConfig = $scopeConfig;
        $this->sdkProxy = $sdkProxy;
        $this->apiProvider = $apiProvider;
        $this->loadMethodInstances = $loadMethodInstances;
    }

    /**
     * Magento Queues do not clear their context between messages.
     * There is an open issue for this https://github.com/magento/magento2/issues/37870
     *
     * The issue for us is that if you change your api key, the old key will still be used by the queue.
     * Therefore, we need to clear the context ourselves.
     */
    public function clean()
    {
        $this->scopeConfig->clean();
        $this->rvvupConfiguration->clean();
        $this->sdkProxy->clean();
        $this->apiProvider->clean();
        $this->loadMethodInstances->clean();
    }
}
