<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use GuzzleHttp\Client;
use Magento\Framework\App\Response\Http as HttpResponse;
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
    /**
     * How long, after a time bounded lookup timed out, further bounded lookups for the same store and currency are
     * skipped. The memory lives in this object, so it spans the rest of one request (and, in a long running process
     * that reuses the object, lets it recover after this long). It is not shared between PHP-FPM requests, so it is
     * not a circuit breaker across requests.
     */
    private const TIMED_OUT_MEMORY_SECONDS = 30;

    /** @var RvvupConfigurationInterface */
    private $config;
    /** @var UserAgentBuilder */
    private $userAgent;
    /** @var GraphQlSdkFactory */
    private $sdkFactory;
    /** @var LoggerInterface */
    private $logger;
    /** @var GetEnvironmentVersionsInterface */
    private $getEnvironmentVersions;
    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var array */
    private $subject;

    /**
     * @var array|null
     */
    private $methods;

    /**
     * @var array
     */
    private $monetizedMethods = [];

    /**
     * When a time bounded lookup last timed out, per store and currency (microtime).
     *
     * @var array<string, float>
     */
    private $boundedLookupTimedOutAt = [];

    /**
     * Time bounded lookups that came back empty, by currency and value. Kept apart from $monetizedMethods, which
     * lookups without a timeout also read: a bounded lookup that failed quickly must not hide the real answer from
     * checkout.
     *
     * @var array
     */
    private $boundedEmptyMethods = [];

    /**
     * @var \Magento\Framework\App\ResponseInterface|null
     */
    private $response;

    /**
     * @param RvvupConfigurationInterface $config
     * @param UserAgentBuilder $userAgent
     * @param GraphQlSdkFactory $sdkFactory
     * @param StoreManagerInterface $storeManager
     * @param GetEnvironmentVersionsInterface $getEnvironmentVersions
     * @param LoggerInterface $logger
     * @param \Magento\Framework\App\ResponseInterface|null $response Used to keep a page rendered after a storefront
     *     lookup timed out out of full page cache. Set in di.xml.
     */
    public function __construct(
        RvvupConfigurationInterface $config,
        UserAgentBuilder $userAgent,
        GraphQlSdkFactory $sdkFactory,
        StoreManagerInterface $storeManager,
        GetEnvironmentVersionsInterface $getEnvironmentVersions,
        LoggerInterface $logger,
        ?\Magento\Framework\App\ResponseInterface $response = null
    ) {
        $this->response = $response;
        $this->config = $config;
        $this->userAgent = $userAgent;
        $this->sdkFactory = $sdkFactory;
        $this->storeManager = $storeManager;
        $this->getEnvironmentVersions = $getEnvironmentVersions;
        $this->logger = $logger;
    }

    /**
     * Clean the proxy caches
     */
    public function clean()
    {
        $this->methods = null;
        $this->monetizedMethods = [];
        $this->boundedLookupTimedOutAt = [];
        $this->boundedEmptyMethods = [];
        $this->subject = [];
    }

    /**
     * Get proxied instance
     * @param string|null $storeId
     * @return GraphQlSdk
     */
    private function getSdkForStore(?string $storeId = null): GraphQlSdk
    {
        if (!isset($storeId) || $storeId === '') {
            $storeId = (string) $this->storeManager->getStore()->getId();
        }
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
    public function getMethods(?string $value = null, ?string $currency = null, ?array $inputOptions = null): array
    {
        // If value & currency are both not null, use separate method.
        if ($value !== null && $currency !== null) {
            return $this->getMethodsByValueAndCurrency($value, $currency, $inputOptions);
        }

        if (!$this->methods) {
            $value = $value === null ? $value : (string) round((float) $value, 2);

            $methods = $this->getSdkForStore()->getMethods($value, $currency, $inputOptions);
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
        return $this->getSdkForStore()->createOrder($orderData);
    }

    public function createPayment($paymentData)
    {
        return $this->getSdkForStore()->createPayment($paymentData);
    }

    /**
     * {@inheritdoc}
     */
    public function updateOrder($data)
    {
        return $this->getSdkForStore()->updateOrder($data);
    }

    /**
     * @param string $orderId
     * @param string|null $storeId
     * @return false|mixed
     */
    public function getOrder(string $orderId, ?string $storeId = null)
    {
        return $this->getSdkForStore($storeId)->getOrder($orderId);
    }

    /**
     * {@inheritdoc}
     */
    public function isOrderRefundable($orderId)
    {
        return $this->getSdkForStore()->isOrderRefundable($orderId);
    }

    /**
     * {@inheritdoc}
     */
    public function isOrderVoidable($orderId)
    {
        return $this->getSdkForStore()->isOrderVoidable($orderId);
    }

    /**
     * {@inheritdoc}
     */
    public function voidPayment($orderId, $paymentId)
    {
        return $this->getSdkForStore()->voidPayment($orderId, $paymentId);
    }

    /**
     * @param string $orderId
     * @param string $paymentId
     * @param string|null $storeId
     * @return false|mixed
     * @throws \JsonException
     * @throws NetworkException
     */
    public function paymentCapture(string $orderId, string $paymentId, ?string $storeId = null)
    {
        return $this->getSdkForStore($storeId)->paymentCapture($orderId, $paymentId);
    }

    /**
     * {@inheritdoc}
     */
    public function refundOrder($orderId, $amount, $reason, $idempotency)
    {
        return $this->getSdkForStore()->refundOrder($orderId, $amount, $reason, $idempotency);
    }

    /**
     * {@inheritdoc}
     */
    public function refundCreate(\Rvvup\Sdk\Inputs\RefundCreateInput $input)
    {
        return $this->getSdkForStore()->refundCreate($input);
    }

    public function cancelPayment(string $paymentId, string $orderId): array
    {
        return $this->getSdkForStore()->cancelPayment($paymentId, $orderId);
    }

    /**
     * {@inheritdoc}
     */
    public function ping(): bool
    {
        return $this->getSdkForStore()->ping();
    }

    /**
     * {@inheritdoc}
     */
    public function registerWebhook(string $url): void
    {
        $this->getSdkForStore()->registerWebhook($url);
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
        $this->getSdkForStore()->createEvent($eventType, $reason, array_merge($additionalData, $data));
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
        if (isset($this->monetizedMethods[$currency][$value])) {
            return $this->monetizedMethods[$currency][$value];
        }

        $threshold = $this->getTimeoutThreshold($inputOptions);
        $timeoutKey = $threshold === null ? null : $this->getTimeoutKey($currency);

        if ($timeoutKey !== null) {
            // Bounded lookups that already came back empty are answered from their own memory, never the shared one.
            if (isset($this->boundedEmptyMethods[$currency][$value])
                // An earlier bounded lookup for this store and currency timed out moments ago, don't wait again.
                || $this->hasRecentlyTimedOut($timeoutKey)
            ) {
                return [];
            }
        }

        $startedAt = microtime(true);
        $methods = $this->filterApiMethods(
            $this->getSdkForStore()->getMethods((string) round((float) $value, 2), $currency, $inputOptions)
        );

        if ($timeoutKey !== null && $methods === []) {
            // The SDK swallows request errors and returns an empty list, so a timeout can only be recognised by an
            // empty result that took at least as long as the timeout. A sooner empty answer might be a real one, or a
            // quick failure, so it is not trusted either: it is only remembered for later bounded lookups and never
            // for lookups without a timeout (checkout), which must still ask the API.
            if ((microtime(true) - $startedAt) >= $threshold) {
                $this->onBoundedLookupTimedOut($timeoutKey, $value, $currency, (float) $threshold);
            } else {
                $this->boundedEmptyMethods[$currency][$value] = true;
            }

            return [];
        }

        $this->monetizedMethods[$currency][$value] = $methods;

        return $methods;
    }

    /**
     * @param string $timeoutKey
     * @param string $value
     * @param string $currency
     * @param float $threshold
     * @return void
     */
    private function onBoundedLookupTimedOut(
        string $timeoutKey,
        string $value,
        string $currency,
        float $threshold
    ): void {
        $this->boundedLookupTimedOutAt[$timeoutKey] = microtime(true);

        $this->logger->warning(
            'Rvvup payment methods lookup returned nothing after hitting the storefront timeout, '
            . 'skipping further bounded lookups for this store and currency for a short while.',
            ['value' => $value, 'currency' => $currency, 'threshold_seconds' => $threshold]
        );

        // The page is about to be rendered without the Rvvup widgets. Don't let full page cache keep that version.
        if ($this->response instanceof HttpResponse) {
            $this->response->setNoCacheHeaders();
        }
    }

    /**
     * The overall timeout in the given SDK options (seconds), or null when the lookup is not time bounded.
     *
     * Only the overall timeout is used, so an empty answer that took longer than the connect timeout but finished
     * within the overall one is not mistaken for a timeout. A connect timeout alone is not detected, which costs at
     * most the connect timeout per distinct lookup.
     *
     * @param array|null $inputOptions
     * @return float|null
     */
    private function getTimeoutThreshold(?array $inputOptions): ?float
    {
        if ($inputOptions === null) {
            return null;
        }

        foreach (['timeout', 'connect_timeout'] as $key) {
            if (isset($inputOptions[$key]) && (float) $inputOptions[$key] > 0) {
                return (float) $inputOptions[$key];
            }
        }

        return null;
    }

    /**
     * @param string $currency
     * @return string
     */
    private function getTimeoutKey(string $currency): string
    {
        return $this->storeManager->getStore()->getId() . '|' . $currency;
    }

    /**
     * @param string $timeoutKey
     * @return bool
     */
    private function hasRecentlyTimedOut(string $timeoutKey): bool
    {
        return isset($this->boundedLookupTimedOutAt[$timeoutKey])
            && (microtime(true) - $this->boundedLookupTimedOutAt[$timeoutKey]) < self::TIMED_OUT_MEMORY_SECONDS;
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
