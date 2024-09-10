<?php

namespace Rvvup\Payments\Service;

use Laminas\Http\Request;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\ResourceModel\Quote\Payment;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterfaceFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Config\RvvupConfigurationInterface;
use Rvvup\Payments\Sdk\Curl;

class PaymentLink
{
    /** @var Curl */
    private $curl;

    /** @var RvvupConfigurationInterface */
    private $config;

    /** @var SerializerInterface */
    private $json;

    /** @var OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory */
    private $orderStatusHistoryFactory;

    /** @var OrderManagementInterface $orderManagement */
    private $orderManagement;

    /**
     * Set via di.xml
     *
     * @var LoggerInterface
     */
    private $logger;

    /** @var CartRepositoryInterface */
    private $cartRepository;

    /** @var Payment */
    private $paymentResource;

    /**
     * @param Curl $curl
     * @param RvvupConfigurationInterface $config
     * @param SerializerInterface $json
     * @param OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory
     * @param OrderManagementInterface $orderManagement
     * @param CartRepositoryInterface $cartRepository
     * @param Payment $paymentResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        Curl                               $curl,
        RvvupConfigurationInterface        $config,
        SerializerInterface                $json,
        OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory,
        OrderManagementInterface           $orderManagement,
        CartRepositoryInterface            $cartRepository,
        Payment                            $paymentResource,
        LoggerInterface                    $logger
    ) {
        $this->curl = $curl;
        $this->config = $config;
        $this->json = $json;
        $this->orderStatusHistoryFactory = $orderStatusHistoryFactory;
        $this->orderManagement = $orderManagement;
        $this->cartRepository = $cartRepository;
        $this->paymentResource = $paymentResource;
        $this->logger = $logger;
    }

    /**
     * @param int $storeId
     * @param float $amount
     * @param string $orderId
     * @param string $currencyCode
     * @return array
     * @throws NoSuchEntityException
     */
    public function createPaymentLink(
        int $storeId,
        float $amount,
        string $orderId,
        string $currencyCode
    ): array {
        $params = $this->getData($amount, $storeId, $orderId, $currencyCode);
        $request = $this->curl->request(Request::METHOD_POST, $this->getApiUrl($storeId), $params);
        return $this->json->unserialize($request->body);
    }

    /**
     * @param int $storeId
     * @param string $paymentLinkId
     * @return array
     * @throws NoSuchEntityException
     */
    public function cancelPaymentLink(
        int $storeId,
        string $paymentLinkId
    ): array {
        $token = $this->config->getBearerToken($storeId);
        $url = $this->getApiUrl($storeId) . '/' . $paymentLinkId;

        $params = [
            'headers' => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            'json' => []
        ];

        $request = $this->curl->request(Request::METHOD_DELETE, $url, $params);
        return $this->json->unserialize($request->body);
    }

    /**
     * @param string $status
     * @param string $orderId
     * @param string $message
     * @return bool
     */
    public function addCommentToOrder(string $status, string $orderId, string $message): bool
    {
        $historyComment = $this->orderStatusHistoryFactory->create();
        $historyComment->setParentId($orderId);
        $historyComment->setIsCustomerNotified(true);
        $historyComment->setIsVisibleOnFront(true);
        $historyComment->setComment($message);
        $historyComment->setStatus($status);
        return $this->orderManagement->addComment($orderId, $historyComment);
    }

    /** @todo move to rest api sdk
     * @param int $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    private function getApiUrl(int $storeId)
    {
        $merchantId = $this->config->getMerchantId($storeId);
        $baseUrl = $this->config->getRestApiUrl($storeId);

        return "$baseUrl/$merchantId/payment-links";
    }

    /**
     * @param PaymentInterface $payment
     * @param string $id
     * @param string $message
     * @return void
     */
    public function savePaymentLink(PaymentInterface $payment, string $id, string $message): void
    {
        try {
            $payment->setAdditionalInformation(Method::PAYMENT_LINK_ID, $id);
            $payment->setAdditionalInformation(Method::PAYMENT_LINK_MESSAGE, $message);
            $this->paymentResource->save($payment);
        } catch (\Exception $e) {
            $this->logger->error('Error saving rvvup payment link: ' . $e->getMessage());
        }
    }

    /**
     * @param OrderInterface $order
     * @return PaymentInterface
     * @throws NoSuchEntityException
     */
    public function getQuotePaymentByOrder(OrderInterface $order): PaymentInterface
    {
        $quoteId = $order->getQuoteId();
        $quote = $this->cartRepository->get($quoteId);
        return $quote->getPayment();
    }

    /**
     * @param string $amount
     * @param int $storeId
     * @param string $orderId
     * @param string $currencyCode
     * @return array
     * @throws NoSuchEntityException
     */
    private function getData(string $amount, int $storeId, string $orderId, string $currencyCode): array
    {
        $postData = [
            'amount' => ['amount' => $amount, 'currency' => $currencyCode],
            'reference' => $orderId,
            'source' => 'MAGENTO_PAYMENT_LINK',
            'reusable' => false
        ];

        $token = $this->config->getBearerToken($storeId);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Idempotency-Key: ' . $orderId,
            'Authorization: Bearer ' . $token
        ];

        return [
            'headers' => $headers,
            'json' => $postData
        ];
    }
}
