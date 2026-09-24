<?php declare(strict_types=1);

namespace Rvvup\Payments\Service;

use Exception;
use GuzzleHttp\Client;
use Rvvup\Payments\Model\Config\RvvupConfigurationInterface;
use Rvvup\Payments\Model\UserAgentBuilder;
use Rvvup\Payments\Sdk\Rest\PaymentMethodTokens;
use Rvvup\Payments\Sdk\Rest\PaymentSessions;
use Rvvup\Sdk\Rest\Options\RvvupClientOptions;
use Rvvup\Sdk\Rest\RvvupClient;

class ApiProvider
{
    /** @var RvvupConfigurationInterface */
    private $config;
    /** @var UserAgentBuilder */
    private $userAgent;

    /** @var array */
    private $clients;

    /** @var array */
    private $paymentSessionsWrappers;

    /** @var array */
    private $paymentMethodTokensWrappers;

    /**
     * @param RvvupConfigurationInterface $config
     * @param UserAgentBuilder $userAgent
     */
    public function __construct(
        RvvupConfigurationInterface $config,
        UserAgentBuilder $userAgent
    ) {
        $this->config = $config;
        $this->userAgent = $userAgent;
        $this->clients = [];
        $this->paymentSessionsWrappers = [];
        $this->paymentMethodTokensWrappers = [];
    }

    /**
     * Clean the proxy caches
     */
    public function clean()
    {
        $this->clients = [];
        $this->paymentSessionsWrappers = [];
        $this->paymentMethodTokensWrappers = [];
    }

    /**
     * @param string $storeId
     * @return RvvupClient
     * @throws Exception
     */
    public function getSdk(string $storeId): RvvupClient
    {
        if (!isset($this->clients[$storeId])) {
            $this->clients[$storeId] = new RvvupClient(
                $this->config->getBearerToken($storeId),
                new RvvupClientOptions(null, null, $this->userAgent->get())
            );
        }
        return $this->clients[$storeId];
    }

    /**
     * @param string $storeId
     * @return PaymentSessions
     * @throws Exception
     */
    public function paymentSessions(string $storeId): PaymentSessions
    {
        if (!isset($this->paymentSessionsWrappers[$storeId])) {
            $sdk = $this->getSdk($storeId);
            $config = $sdk->configuration();
            $this->paymentSessionsWrappers[$storeId] = new PaymentSessions(
                $config->getHost(),
                $sdk->getMerchantId(),
                $config->getAccessToken(),
                new Client()
            );
        }
        return $this->paymentSessionsWrappers[$storeId];
    }

    /**
     * @param string $storeId
     * @return PaymentMethodTokens
     * @throws Exception
     */
    public function paymentMethodTokens(string $storeId): PaymentMethodTokens
    {
        if (!isset($this->paymentMethodTokensWrappers[$storeId])) {
            $sdk = $this->getSdk($storeId);
            $config = $sdk->configuration();
            $this->paymentMethodTokensWrappers[$storeId] = new PaymentMethodTokens(
                $config->getHost(),
                $sdk->getMerchantId(),
                $config->getAccessToken(),
                new Client()
            );
        }
        return $this->paymentMethodTokensWrappers[$storeId];
    }
}
