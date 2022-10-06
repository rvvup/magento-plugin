<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Rvvup\Sdk\GraphQlSdkFactory;
use Rvvup\Sdk\GraphQlSdk;

class SdkProxy
{
    /** @var ConfigInterface */
    private $config;
    /** @var UserAgentBuilder */
    private $userAgent;
    /** @var GraphQlSdkFactory */
    private $sdkFactory;
    /** @var LoggerInterface */
    private $logger;
    /** @var GraphQlSdk */
    private $subject;
    /** @var array */
    private $methods;

    /**
     * @param ConfigInterface $config
     * @param UserAgentBuilder $userAgent
     * @param GraphQlSdkFactory $sdkFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigInterface $config,
        UserAgentBuilder $userAgent,
        GraphQlSdkFactory $sdkFactory,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->userAgent = $userAgent;
        $this->sdkFactory = $sdkFactory;
        $this->logger = $logger;
    }

    /**
     * Get proxied instance
     *
     * @return GraphQlSdk
     */
    private function getSubject(): GraphQlSdk
    {
        if (!$this->subject) {
            $endpoint = $this->config->getEndpoint();
            $merchant = $this->config->getMerchantId();
            $authToken = $this->config->getAuthToken();
            $debugMode = $this->config->isDebugEnabled();
            /** @var GraphQlSdk instance */
            $this->subject = $this->sdkFactory->create([
                'endpoint' => $endpoint,
                'merchantId' => $merchant,
                'authToken' => $authToken,
                'userAgent' => $this->userAgent->get(),
                'debug' => $debugMode,
                'adapter' => (new Client()),
                'logger' => $this->logger
            ]);
        }
        return $this->subject;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethods(string $cartTotal, string $currency, ?array $inputOptions = null): array
    {
        if (!$this->methods) {
            $methods = $this->getSubject()->getMethods((string) round((float) $cartTotal, 2), $currency, $inputOptions);
            /**
             * Due to all Rvvup methods having the same `sort_order`values the way Magento sorts methods we need to
             * reverse the array so that they are presented in the order specified in the Rvvup dashboard
             */
            $this->methods = array_reverse($methods);
        }
        return $this->methods;
    }

    /**
     * {@inheritdoc}
     */
    public function createOrder($orderData)
    {
        return $this->getSubject()->createOrder($orderData);
    }

    /**
     * {@inheritdoc}
     */
    public function getOrder($orderId)
    {
        return $this->getSubject()->getOrder($orderId);
    }

    /**
     * {@inheritdoc}
     */
    public function isOrderRefundable($orderId)
    {
        return $this->getSubject()->isOrderRefundable($orderId);
    }

    /**
     * {@inheritdoc}
     */
    public function refundOrder($orderId, $amount, $reason, $idempotency)
    {
        return $this->getSubject()->refundOrder($orderId, $amount, $reason, $idempotency);
    }

    /**
     * {@inheritdoc}
     */
    public function ping(): bool
    {
        return $this->getSubject()->ping();
    }

    /**
     * {@inheritdoc}
     */
    public function registerWebhook(string $url): void
    {
        $this->getSubject()->registerWebhook($url);
    }
}
