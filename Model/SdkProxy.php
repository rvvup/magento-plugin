<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use GuzzleHttp\Client;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\Config\RvvupConfigurationInterface;
use Rvvup\Payments\Model\Environment\GetEnvironmentVersionsInterface;
use Rvvup\Sdk\Exceptions\NetworkException;
use Rvvup\Sdk\GraphQlSdkFactory;
use Rvvup\Sdk\GraphQlSdk;

class SdkProxy
{
    /** @var RvvupConfigurationInterface */
    private $config;
    /** @var UserAgentBuilder */
    private $userAgent;
    /** @var GraphQlSdkFactory */
    private $sdkFactory;
    /** @var LoggerInterface */
    private $logger;
    /** @var GraphQlSdk */
    private $subject;

    /**
     * @var \Rvvup\Payments\Model\Environment\GetEnvironmentVersionsInterface
     */
    private $getEnvironmentVersions;

    /**
     * @var array|null
     */
    private $methods;

    /**
     * @var array
     */
    private $monetizedMethods = [];

    /** @var StoreManagerInterface */
    private $storeManager;

    /**
     * @param RvvupConfigurationInterface $config
     * @param UserAgentBuilder $userAgent
     * @param GraphQlSdkFactory $sdkFactory
     * @param StoreManagerInterface $storeManager
     * @param GetEnvironmentVersionsInterface $getEnvironmentVersions
     * @param LoggerInterface $logger
     */
    public function __construct(
        RvvupConfigurationInterface $config,
        UserAgentBuilder $userAgent,
        GraphQlSdkFactory $sdkFactory,
        StoreManagerInterface $storeManager,
        GetEnvironmentVersionsInterface $getEnvironmentVersions,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->userAgent = $userAgent;
        $this->sdkFactory = $sdkFactory;
        $this->storeManager = $storeManager;
        $this->getEnvironmentVersions = $getEnvironmentVersions;
        $this->logger = $logger;
    }

    /**
     * Get proxied instance
     *
     * @return GraphQlSdk
     */
    private function getSubject(): GraphQlSdk
    {
        $storeId = (string) $this->storeManager->getStore()->getId();
        if (!isset($this->subject[$storeId])) {
            $endpoint = $this->config->getGraphQlUrl($storeId);
            $merchant = $this->config->getMerchantId($storeId);
            $authToken = $this->config->getBasicAuthToken($storeId);
            $debugMode = $this->config->isDebugEnabled($storeId);
            /** @var GraphQlSdk instance */
            $this->subject[$storeId] = $this->sdkFactory->create([
                'endpoint' => $endpoint,
                'merchantId' => $merchant,
                'authToken' => $authToken,
                'userAgent' => $this->userAgent->get(),
                'debug' => $debugMode,
                'adapter' => (new Client()),
                'logger' => $this->logger
            ]);
        }
        return $this->subject[$storeId];
    }

    /**
     * @param string|null $value
     * @param string|null $currency
     * @param array|null $inputOptions
     * @return array
     */
    public function getMethods(string $value = null, string $currency = null, ?array $inputOptions = null): array
    {
        // If value & currency are both not null, use separate method.
        if ($value !== null && $currency !== null) {
            return $this->getMethodsByValueAndCurrency($value, $currency, $inputOptions);
        }

        if (!$this->methods) {
            $value = $value === null ? $value : (string) round((float) $value, 2);

            $methods = $this->getSubject()->getMethods($value, $currency, $inputOptions);
            /**
             * Due to all Rvvup methods having the same `sort_order`values the way Magento sorts methods we need to
             * reverse the array so that they are presented in the order specified in the Rvvup dashboard
             */
            $this->methods = $this->filterApiMethods($methods);
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
    public function isOrderVoidable($orderId)
    {
        return $this->getSubject()->isOrderVoidable($orderId);
    }

    /**
     * {@inheritdoc}
     */
    public function voidPayment($orderId, $paymentId, $reason = null)
    {
        return $this->getSubject()->voidPayment($orderId, $paymentId, $reason);
    }

    /**
     * @param string $orderId
     * @param string $paymentId
     * @return false|mixed
     * @throws \JsonException
     * @throws NetworkException
     */
    public function paymentCapture(string $orderId, string $paymentId)
    {
        return $this->getSubject()->paymentCapture($orderId, $paymentId);
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
    public function confirmCardAuthorization(
        string $paymentId,
        string $orderId,
        string $authorizationResponse,
        ?string $threeDSecureResponse
    ): array {
        return $this->getSubject()->confirmCardAuthorization(
            $paymentId,
            $orderId,
            $authorizationResponse,
            $threeDSecureResponse
        );
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
