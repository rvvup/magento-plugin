<?php

declare(strict_types=1);

namespace Rvvup\Payments\Service;

use Laminas\Http\Request;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\ResourceModel\Quote\Payment;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Config\RvvupConfigurationInterface;
use Rvvup\Payments\Sdk\Curl;

class VirtualCheckout
{
    /** @var SerializerInterface */
    private $json;

    /** @var Curl */
    private $curl;

    /** @var RvvupConfigurationInterface */
    private $config;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var Payment */
    private $paymentResource;

    /** @var Emulation */
    private $emulation;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var LoggerInterface */
    private $logger;

    /** @var PaymentLink */
    private $paymentLinkService;

    /** @var UrlInterface */
    private $url;

    /**
     * @param RvvupConfigurationInterface $config
     * @param OrderRepositoryInterface $orderRepository
     * @param Payment $paymentResource
     * @param SerializerInterface $json
     * @param Curl $curl
     * @param Emulation $emulation
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param PaymentLink $paymentLinkService
     * @param UrlInterface $url
     */
    public function __construct(
        RvvupConfigurationInterface $config,
        OrderRepositoryInterface $orderRepository,
        Payment                  $paymentResource,
        SerializerInterface      $json,
        Curl                     $curl,
        Emulation                $emulation,
        LoggerInterface          $logger,
        StoreManagerInterface    $storeManager,
        PaymentLink              $paymentLinkService,
        UrlInterface             $url
    ) {
        $this->config = $config;
        $this->orderRepository = $orderRepository;
        $this->paymentResource = $paymentResource;
        $this->json = $json;
        $this->curl = $curl;
        $this->emulation = $emulation;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->paymentLinkService = $paymentLinkService;
        $this->url = $url;
    }

    /**
     * @param string $amount
     * @param string $storeId
     * @param string $orderId
     * @param string $currencyCode
     * @return array
     */
    public function createVirtualCheckout(string $amount, string $storeId, string $orderId, string $currencyCode): array
    {
        $order = $this->orderRepository->get($orderId);
        $params = $this->buildRequestData($amount, $storeId, $order->getIncrementId(), $currencyCode);
        $request = $this->curl->request(Request::METHOD_POST, $this->getApiUrl($storeId), $params);
        $body = $this->json->unserialize($request->body);
        $this->processResponse($order, $body);
        return $body;
    }

    /**
     * @param string $virtualCheckoutId
     * @param string $storeId
     * @param OrderInterface $order
     * @return string|null
     */
    public function getRvvupIdByMotoId(string $virtualCheckoutId, string $storeId, OrderInterface $order): ?string
    {
        try {
            $paymentSessionId = $this->getPaymentSessionId($virtualCheckoutId, $storeId);
            $id = $this->getRvvupIdByPaymentSessionId($storeId, $virtualCheckoutId, $paymentSessionId, $order);
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to get Rvvup Id by Moto Id',
                [
                    $virtualCheckoutId,
                    $order->getId(),
                    $storeId,
                    $e->getMessage()
                ]
            );
            return null;
        }

        return $id;
    }

    /**
     * @param int $orderId
     * @return string
     */
    public function getOrderViewUrl(int $orderId): string
    {
        $stores = $this->storeManager->getStores();
        $adminStoreId = 0;
        foreach ($stores as $store) {
            if ($store->getCode() === Store::ADMIN_CODE) {
                $adminStoreId = $store->getId();
            }
        }

        $this->emulation->startEnvironmentEmulation($adminStoreId, Area::AREA_ADMINHTML);
        $url = $this->url->getUrl(
            'sales/order/view',
            [
                'order_id' => $orderId,
                '_type' => UrlInterface::URL_TYPE_WEB,
                '_scope' => $adminStoreId
            ]
        );
        $this->emulation->stopEnvironmentEmulation();
        return $url;
    }

    /**
     * @param string $storeId
     * @param string $virtualCheckoutId
     * @param string $paymentSessionId
     * @param OrderInterface $order
     * @return mixed
     * @throws AlreadyExistsException
     */
    private function getRvvupIdByPaymentSessionId(
        string $storeId,
        string $virtualCheckoutId,
        string $paymentSessionId,
        OrderInterface $order
    ): string {
        $url = $this->getApiUrl($storeId) . '/' . $virtualCheckoutId .'/payment-sessions/' . $paymentSessionId;
        $headers = $this->getHeaders($storeId);
        $request = $this->curl->request(
            Request::METHOD_GET,
            $url,
            [
                'headers' => $headers,
                'json' => []
            ]
        );
        $body = $this->json->unserialize($request->body);
        $id = $body['id'];
        $payment = $order->getPayment();
        $payment->setAdditionalInformation(Method::ORDER_ID, $id);
        $this->paymentResource->save($payment);
        $this->orderRepository->save($order);
        return $id;
    }

    /**
     * @param string $virtualCheckoutId
     * @param string $storeId
     * @return string
     */
    private function getPaymentSessionId(string $virtualCheckoutId, string $storeId): string
    {
        $url = $this->getApiUrl($storeId) . '/' . $virtualCheckoutId;

        $headers = $this->getHeaders($storeId);
        $request = $this->curl->request(
            Request::METHOD_GET,
            $url,
            [
                'headers' => $headers,
                'json' => []
            ]
        );
        $body = $this->json->unserialize($request->body);
        return $body['paymentSessionIds'][0];
    }

    /**
     * @param OrderInterface $order
     * @param array $body
     * @return void
     */
    private function processResponse(OrderInterface $order, array $body): void
    {
        $motoId = $body['id'];
        try {
            $payment = $this->paymentLinkService->getQuotePaymentByOrder($order);
            $payment->setAdditionalInformation(Method::MOTO_ID, $motoId);
            $this->paymentResource->save($payment);
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to process rvvup response for virtual checkout',
                [
                    $order->getId(),
                    $this->json->serialize($body),
                    $e->getMessage()
                ]
            );
        }
    }

    /** @todo move to rest api sdk
     * @param string $storeId
     * @return string
     */
    private function getApiUrl(string $storeId): string
    {
        $merchantId = $this->config->getMerchantId($storeId);
        $baseUrl = $this->config->getRestApiUrl($storeId);
        return "$baseUrl/$merchantId/checkouts";
    }

    /**
     * @param string $amount
     * @param string $storeId
     * @param string $orderId
     * @param string $currencyCode
     * @return array
     */
    private function buildRequestData(string $amount, string $storeId, string $orderId, string $currencyCode): array
    {
        $url = $this->url->getBaseUrl(['_scope' => $storeId, '_type' => UrlInterface::URL_TYPE_WEB])
            . "rvvup/redirect/in?store_id=$storeId&checkout_id={{CHECKOUT_ID}}";

        $postData = [
            'amount' => ['amount' => $amount, 'currency' => $currencyCode],
            'reference' => $orderId,
            'source' => 'MAGENTO_MOTO',
            'successUrl' => $url,
            'pendingUrl' => $url
        ];

        $headers = $this->getHeaders($storeId, $orderId);

        return [
            'headers' => $headers,
            'json' => $postData
        ];
    }

    /**
     * @param string $storeId
     * @param string|null $orderId
     * @return string[]
     */
    private function getHeaders(string $storeId, ?string $orderId = null): array
    {
        $token = $this->config->getBearerToken($storeId);
        $result =  [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ];

        if ($orderId) {
            $result[] = 'Idempotency-Key: ' . $orderId;
        }

        return $result;
    }
}
