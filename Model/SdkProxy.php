<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\Environment\GetEnvironmentVersionsInterface;
use Rvvup\Sdk\GraphQlSdkFactory;
use Rvvup\Sdk\GraphQlSdk;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;

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

    /** @var GetEnvironmentVersionsInterface */
    private $getEnvironmentVersions;

    /**
     * @var array
     */
    private $monetizedMethods = [];

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param ConfigInterface $config
     * @param UserAgentBuilder $userAgent
     * @param GraphQlSdkFactory $sdkFactory
     * @param GetEnvironmentVersionsInterface $getEnvironmentVersions
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConfigInterface $config,
        UserAgentBuilder $userAgent,
        GraphQlSdkFactory $sdkFactory,
        GetEnvironmentVersionsInterface $getEnvironmentVersions,
        CacheInterface $cache,
        SerializerInterface $serializer,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->userAgent = $userAgent;
        $this->sdkFactory = $sdkFactory;
        $this->getEnvironmentVersions = $getEnvironmentVersions;
        $this->cache = $cache;
        $this->serializer = $serializer;
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
     * @param string|null $value
     * @param string|null $currency
     * @param array|null $inputOptions
     * @return array
     */
    public function getMethods(string $value = null, string $currency = null, ?array $inputOptions = null): array
    {
        $identifier = $value . '_' . $currency;

        if (!$methods = $this->cache->load($identifier)) {

            // If value & currency are both not null, use separate method.
            if ($value !== null && $currency !== null) {
                $methods = $this->getMethodsByValueAndCurrency($value, $currency, $inputOptions);
                $this->saveMethodsToCache($methods, $identifier);
                return $methods;
            }

            $value = $value === null ? $value : (string) round((float) $value, 2);

            $methods = $this->getSubject()->getMethods($value, $currency, $inputOptions);
            /**
             * Due to all Rvvup methods having the same `sort_order`values the way Magento sorts methods we need to
             * reverse the array so that they are presented in the order specified in the Rvvup dashboard
             */
            $methods = $this->filterApiMethods($methods);
            $this->saveMethodsToCache($methods, $identifier);

            return $methods;
        }

        return $this->serializer->unserialize($methods);
    }

    /**
     * @param array $methods
     * @param string $identifier
     * @return void
     */
    private function saveMethodsToCache(array $methods, string $identifier): void
    {
        $lifetime = $this->getShortestLifetime($methods);
        if ($lifetime) {
            $lifetime =  $lifetime - strtotime('now');
            $this->cache->save($this->serializer->serialize($methods), $identifier, [], $lifetime);
        } else {
            $this->cache->save($this->serializer->serialize($methods), $identifier, [], strtotime('15 mins'));
        }
    }

    /**
     * @param array $methods
     * @return int
     */
    private function getShortestLifetime(array $methods): int
    {
        $lifetime = 0;

        foreach ($methods as $method) {
            if (isset($method['limits']['expiresAt'])) {
                if (!$lifetime) {
                    $lifetime = strtotime($method['limits']['expiresAt']);
                }

                if (strtotime($method['limits']['expiresAt']) < $lifetime) {
                    $lifetime = strtotime($method['limits']['expiresAt']);
                }
            }
        }
        return (int)$lifetime;
    }

    /**
     * {@inheritdoc}
     */
    public function createOrder($orderData)
    {
        return $this->getSubject()->createOrder($orderData);
    }

    public function createPayment($paymentData)
    {
        return $this->getSubject()->createPayment($paymentData);
    }

    /**
     * {@inheritdoc}
     */
    public function updateOrder($data)
    {
        return $this->getSubject()->updateOrder($data);
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
    public function refundCreate(\Rvvup\Sdk\Inputs\RefundCreateInput $input)
    {
        return $this->getSubject()->refundCreate($input);
    }

    public function cancelPayment(string $paymentId, string $orderId): array
    {
        return $this->getSubject()->cancelPayment($paymentId, $orderId);
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

    /**
     * @param string $eventType
     * @param string $reason
     * @param array $additionalData
     * @return void
     * @throws \Exception
     */
    public function createEvent(string $eventType, string $reason, array $additionalData = []): void
    {
        $environmentVersions = $this->getEnvironmentVersions->execute();

        $data = [
            'plugin' => $environmentVersions['rvvp_module_version'],
            'php' => $environmentVersions['php_version'],
            'magento_edition' => $environmentVersions['magento_version']['edition'],
            'magento' => $environmentVersions['magento_version']['version'],
        ];

        // Add any data send by the event, but keep core data untouched.
        $this->getSubject()->createEvent($eventType, $reason, array_merge($additionalData, $data));
    }

    /**
     * Get the Rvvup payment methods available for a specific value/productPrice/total & a specific currency.
     *
     * @param string $value
     * @param string $currency
     * @param array|null $inputOptions
     * @return array
     */
    private function getMethodsByValueAndCurrency(string $value, string $currency, ?array $inputOptions = null): array
    {
        if (!isset($this->monetizedMethods[$currency][$value])) {
            $methods = $this->getSubject()->getMethods((string) round((float) $value, 2), $currency, $inputOptions);

            $this->monetizedMethods[$currency][$value] = $this->filterApiMethods($methods);
        }

        return $this->monetizedMethods[$currency][$value];
    }

    /**
     * Filter API returned methods.
     *
     * Rvvup methods having the same `sort_order`values in Magento, while Rvvup API call returns sorted methods.
     * We get the array values & filter the results. Sorting order should be kept as in the Portal.
     *
     * @param array $methods
     * @return array
     */
    private function filterApiMethods(array $methods): array
    {
        return array_filter(array_values($methods), static function ($method) {
            return isset($method['name']);
        });
    }
}
