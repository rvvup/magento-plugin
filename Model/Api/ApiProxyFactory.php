<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\Api;

use GuzzleHttp\Client;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\Environment\GetEnvironmentVersionsInterface;
use Rvvup\Payments\Model\UserAgentBuilder;
use Rvvup\Sdk\GraphQlSdk;
use Rvvup\Sdk\GraphQlSdkFactory;

class ApiProxyFactory
{
    /** @var ConfigInterface */
    private $config;
    /** @var UserAgentBuilder */
    private $userAgent;
    /** @var GraphQlSdkFactory */
    private $sdkFactory;
    /** @var LoggerInterface */
    private $logger;

    /** @var array */
    private $subject;

    /**
     * @param ConfigInterface $config
     * @param UserAgentBuilder $userAgent
     * @param GraphQlSdkFactory $sdkFactory
     * @param StoreManagerInterface $storeManager
     * @param GetEnvironmentVersionsInterface $getEnvironmentVersions
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigInterface   $config,
        UserAgentBuilder  $userAgent,
        GraphQlSdkFactory $sdkFactory,
        LoggerInterface   $logger
    )
    {
        $this->config = $config;
        $this->userAgent = $userAgent;
        $this->sdkFactory = $sdkFactory;
        $this->logger = $logger;
    }

    /**
     * Get store-aware api proxy
     *
     * @return ApiProxy
     */
    function forStore(int $storeId): ApiProxy
    {
        if (isset($this->subject[$storeId])) {
            return $this->subject[$storeId];
        }

        $endpoint = $this->config->getEndpoint();
        $merchant = $this->config->getMerchantIdByStore($storeId);
        $authToken = $this->config->getAuthTokenByStore($storeId);
        $debugMode = $this->config->isDebugEnabled();
        /** @var GraphQlSdk instance */
        $this->subject[$storeId] = new ApiProxy($this->sdkFactory->create([
            'endpoint' => $endpoint,
            'merchantId' => $merchant,
            'authToken' => $authToken,
            'userAgent' => $this->userAgent->get(),
            'debug' => $debugMode,
            'adapter' => (new Client()),
            'logger' => $this->logger
        ]));
        return $this->subject[$storeId];
    }
}
