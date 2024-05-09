<?php

declare(strict_types=1);

namespace Rvvup\Payments\Service;

use Laminas\Http\Request;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Model\ResourceModel\Quote\Payment;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Block\Adminhtml\Order\View\Info;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Config;
use Rvvup\Payments\Sdk\Curl;

class VirtualCheckout
{
    /** @var SerializerInterface */
    private $json;

    /** @var Curl */
    private $curl;

    /** @var Config */
    private $config;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var Payment */
    private $paymentResource;

    /** @var Info */
    private $info;

    /** @var Emulation */
    private $emulation;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var LoggerInterface */
    private $logger;

    /** @var PaymentLink */
    private $paymentLinkService;

    /**
     * @param Config $config
     * @param OrderRepositoryInterface $orderRepository
     * @param Payment $paymentResource
     * @param SerializerInterface $json
     * @param Curl $curl
     * @param Info $info
     * @param Emulation $emulation
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param PaymentLink $paymentLinkService
     */
    public function __construct(
        Config                   $config,
        OrderRepositoryInterface $orderRepository,
        Payment                  $paymentResource,
        SerializerInterface      $json,
        Curl                     $curl,
        Info                     $info,
        Emulation                $emulation,
        LoggerInterface          $logger,
        StoreManagerInterface    $storeManager,
        PaymentLink              $paymentLinkService
    ) {
        $this->config = $config;
        $this->orderRepository = $orderRepository;
        $this->paymentResource = $paymentResource;
        $this->json = $json;
        $this->curl = $curl;
        $this->info = $info;
        $this->emulation = $emulation;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->paymentLinkService = $paymentLinkService;
    }

    /**
     * @param string $amount
     * @param string $storeId
     * @param string $orderId
     * @param string $currencyCode
     * @return array
     * @throws NoSuchEntityException
     */
    public function createVirtualCheckout(string $amount, string $storeId, string $orderId, string $currencyCode): array
    {
        $params = $this->buildRequestData($amount, $storeId, $orderId, $currencyCode);
        $request = $this->curl->request(Request::METHOD_POST, $this->getApiUrl($storeId), $params);
        $body = $this->json->unserialize($request->body);
        $this->processResponse($orderId, $body);
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
        $url = $this->info->getViewUrl($orderId);
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
     * @throws NoSuchEntityException
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
     * @throws NoSuchEntityException
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
     * @param string $orderId
     * @param array $body
     * @return void
     */
    private function processResponse(string $orderId, array $body): void
    {
        $motoId = $body['id'];
        try {
            $order = $this->orderRepository->get($orderId);
            $payment = $this->paymentLinkService->getQuotePaymentByOrder($order);
            $payment->setAdditionalInformation(Method::MOTO_ID, $motoId);

            if ($payment->getAdditionalInformation(Method::PAYMENT_LINK_ID)) {
                $paymentLinkId = $payment->getAdditionalInformation(Method::PAYMENT_LINK_ID);
            } elseif ($order->getPayment()->getAdditionalInformation(Method::PAYMENT_LINK_ID)) {
                $paymentLinkId = $order->getPayment()->getAdditionalInformation(Method::PAYMENT_LINK_ID);
            }
            if (isset($paymentLinkId)) {
                $this->paymentLinkService->cancelPaymentLink((string)$order->getStoreId(), $paymentLinkId);
            }

            $this->paymentResource->save($payment);
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to process rvvup response for virtual checkout',
                [
                    $orderId,
                    $this->json->serialize($body),
                    $e->getMessage()
                ]
            );
        }
    }

    /** @todo move to rest api sdk
     * @param string $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    private function getApiUrl(string $storeId)
    {
        $merchantId = $this->config->getMerchantId(ScopeInterface::SCOPE_STORE, $storeId);
        $baseUrl = $this->config->getEndpoint(ScopeInterface::SCOPE_STORE, $storeId);
        return str_replace('graphql', "api/2024-03-01/$merchantId/checkouts", $baseUrl);
    }

    /**
     * @param string $amount
     * @param string $storeId
     * @param string $orderId
     * @param string $currencyCode
     * @return array
     * @throws NoSuchEntityException
     */
    private function buildRequestData(string $amount, string $storeId, string $orderId, string $currencyCode): array
    {
        $url = $this->info->getBaseUrl(['_scope' => $storeId])
            . "rvvup/redirect/in?store_id=$storeId&checkout_id={{CHECKOUT_ID}}";

        $postData = [
            'amount' => ['amount' => $amount, 'currency' => $currencyCode],
            'reference' => $orderId,
            'source' => 'MAGENTO_MOTO',
            'successUrl' => $url
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
     * @throws NoSuchEntityException
     */
    private function getHeaders(string $storeId, ?string $orderId = null)
    {
        $token = $this->config->getJwtConfig(ScopeInterface::SCOPE_STORE, $storeId);
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
