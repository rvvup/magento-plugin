<?php

namespace Rvvup\Payments\Service;

use Laminas\Http\Request;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterfaceFactory;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\ResourceModel\Quote\Payment;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Store\Model\ScopeInterface;
use Rvvup\Payments\Model\Config;
use Rvvup\Payments\Sdk\Curl;
use Psr\Log\LoggerInterface;

class PaymentLink
{
    /** @var Curl */
    private $curl;

    /** @var Config */
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

    /** @var Payment */
    private $quotePaymentResource;

    /**
     * @param Curl $curl
     * @param Config $config
     * @param SerializerInterface $json
     * @param OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory
     * @param OrderManagementInterface $orderManagement
     * @param Payment $quotePaymentResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        Curl                               $curl,
        Config                             $config,
        SerializerInterface                $json,
        OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory,
        OrderManagementInterface           $orderManagement,
        Payment                            $quotePaymentResource,
        LoggerInterface                    $logger
    ) {
        $this->curl = $curl;
        $this->config = $config;
        $this->json = $json;
        $this->orderStatusHistoryFactory = $orderStatusHistoryFactory;
        $this->orderManagement = $orderManagement;
        $this->quotePaymentResource = $quotePaymentResource;
        $this->logger = $logger;
    }

    /**
     * @param string $storeId
     * @param float $amount
     * @param string $orderId
     * @param string $currencyCode
     * @return array
     * @throws NoSuchEntityException
     */
    public function createPaymentLink(
        string $storeId,
        float $amount,
        string $orderId,
        string $currencyCode
    ): array {
        $params = $this->getData($amount, $storeId, $orderId, $currencyCode);
        $request = $this->curl->request(Request::METHOD_POST, $this->getApiUrl($storeId), $params);
        return $this->json->unserialize($request->body);
    }

    /**
     * @param string $storeId
     * @param string $paymentLinkId
     * @return array
     * @throws NoSuchEntityException
     */
    public function cancelPaymentLink(
        string $storeId,
        string $paymentLinkId
    ): array {
        $token = $this->config->getJwtConfig(ScopeInterface::SCOPE_STORE, $storeId);
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
     * @param string $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    private function getApiUrl(string $storeId)
    {
        $merchantId = $this->config->getMerchantId(ScopeInterface::SCOPE_STORE, $storeId);
        $baseUrl = $this->config->getEndpoint(ScopeInterface::SCOPE_STORE, $storeId);
        $baseUrl = str_replace('graphql', 'api/2024-03-01', $baseUrl);

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
            $payment->setAdditionalInformation('rvvup_payment_link_id', $id);
            $payment->setAdditionalInformation('rvvup_payment_link_message', $message);
            $this->quotePaymentResource->save($payment);
        } catch (\Exception $e) {
            $this->logger->error('Error saving rvvup payment link: ' . $e->getMessage());
        }
    }

    /**
     * @param string $amount
     * @param string $storeId
     * @param string $orderId
     * @param string $currencyCode
     * @return array
     * @throws NoSuchEntityException
     */
    private function getData(string $amount, string $storeId, string $orderId, string $currencyCode): array
    {
        $postData = [
            'amount' => ['amount' => $amount, 'currency' => $currencyCode],
            'reference' => $orderId,
            'source' => 'MAGENTO_PAYMENT_LINK',
            'reusable' => false
        ];

        $token = $this->config->getJwtConfig(ScopeInterface::SCOPE_STORE, $storeId);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Idempotency-Key' => $orderId,
            'Authorization: Bearer ' . $token
        ];

        return [
            'headers' => $headers,
            'json' => $postData
        ];
    }
}
