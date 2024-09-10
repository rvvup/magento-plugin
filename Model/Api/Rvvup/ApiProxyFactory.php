<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\Api\Rvvup;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\Config\RvvupConfigurationInterface;
use Rvvup\Payments\Model\UserAgentBuilder;
use Rvvup\Sdk\GraphQlSdk;
use Rvvup\Sdk\GraphQlSdkFactory;

class ApiProxyFactory
{
    /** @var RvvupConfigurationInterface */
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
     * @param RvvupConfigurationInterface $config
     * @param UserAgentBuilder $userAgent
     * @param GraphQlSdkFactory $sdkFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        RvvupConfigurationInterface $config,
        UserAgentBuilder            $userAgent,
        GraphQlSdkFactory           $sdkFactory,
        LoggerInterface             $logger
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

        $endpoint = $this->config->getGraphQlUrl($storeId);
        $merchant = $this->config->getMerchantId($storeId);
        $authToken = $this->config->getBasicAuthToken($storeId);
        $debugMode = $this->config->isDebugEnabled($storeId);
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
