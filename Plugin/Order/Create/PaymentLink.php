<?php

namespace Rvvup\Payments\Plugin\Order\Create;

use Laminas\Http\Request;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Model\ResourceModel\Quote\Payment;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterfaceFactory;
use Magento\Sales\Model\AdminOrder\Create;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Rvvup\Payments\Model\Config;
use Rvvup\Payments\Model\RvvupConfigProvider;
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

    /** @var Http */
    private $request;

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
     * @param Http $request
     * @param Payment $quotePaymentResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        Curl                               $curl,
        Config                             $config,
        SerializerInterface                $json,
        OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory,
        OrderManagementInterface           $orderManagement,
        Http                               $request,
        Payment                            $quotePaymentResource,
        LoggerInterface                    $logger
    ) {
        $this->curl = $curl;
        $this->config = $config;
        $this->json = $json;
        $this->orderStatusHistoryFactory = $orderStatusHistoryFactory;
        $this->orderManagement = $orderManagement;
        $this->request = $request;
        $this->quotePaymentResource = $quotePaymentResource;
        $this->logger = $logger;
    }

    /**
     * @param Create $subject
     * @param Create $result
     * @param array $data
     * @return Create
     * @throws NoSuchEntityException
     */
    public function afterImportPostData(Create $subject, Create $result, array $data): Create
    {
        if ($result->getQuote() && $result->getQuote()->getPayment()->getMethod() == RvvupConfigProvider::CODE) {
            if (isset($data['comment'])) {
                $quote = $result->getQuote();
                $storeId = (string)$quote->getStore()->getId();
                $amount = (float)$quote->getGrandTotal();
                $orderId = $quote->reserveOrderId()->getReservedOrderId();
                $currencyCode = $quote->getQuoteCurrencyCode();
                $id = $this->createRvvupPayByLink($storeId, $amount, $orderId, $currencyCode, $subject, $data);
                $this->savePaymentLink($subject, $id);
            }
        }
        return $result;
    }

    /** Send separate confirmation if merchant is not
     * informing customer with order success email
     * @param Create $subject
     * @param Order $result
     * @return Order
     * @throws NoSuchEntityException
     */
    public function afterCreateOrder(Create $subject, Order $result): Order
    {
        $order = $this->request->getPost('order');
        if (!(isset($order['send_confirmation']) && $order['send_confirmation'])) {
            $id = $this->createRvvupPayByLink(
                (string)$result->getStoreId(),
                $result->getGrandTotal(),
                $result->getId(),
                $result->getOrderCurrencyCode(),
                $subject,
                ['status' => $result->getStatus()]
            );
            $this->savePaymentLink($subject, $id);
        }

        return $result;
    }

    /**
     * @param Create $subject
     * @param string $id
     * @return void
     */
    private function savePaymentLink(Create $subject, string $id): void
    {
        try {
            $payment = $subject->getQuote()->getPayment();
            $payment->setAdditionalInformation('rvvup_payment_link_id', $id);
            $this->quotePaymentResource->save($payment);
        } catch (\Exception $e) {
            $this->logger->error('Error saving rvvup payment link: ' . $e->getMessage());
        }
    }

    /**
     * Create Rvvup pay-by-link and save it to comment
     * @param string $storeId
     * @param float $amount
     * @param string $orderId
     * @param string $currencyCode
     * @param Create $subject
     * @param array $data
     * @return string|null
     */
    private function createRvvupPayByLink(
        string $storeId,
        float $amount,
        string $orderId,
        string $currencyCode,
        Create $subject,
        array $data
    ): ?string {
        try {
            $amount = number_format($amount, 2, '.', '');
            $params = $this->getData($amount, $storeId, $orderId, $currencyCode);

            $request = $this->curl->request(Request::METHOD_POST, $this->getApiUrl($storeId), $params);
            $body = $this->json->unserialize($request->body);
            $this->processApiResponse($body, $amount, $subject, $data, $orderId);
            return $body['id'];
        } catch (\Exception $e) {
            $this->logger->error('Rvvup payment link creation failed with error: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * @param array $body
     * @param string $amount
     * @param Create $subject
     * @param array $data
     * @param string $orderId
     * @return void
     */
    private function processApiResponse(array $body, string $amount, Create $subject, array $data, string $orderId): void
    {
        if ($body['status'] == 'ACTIVE') {
            if ($amount == $body['amount']['amount']) {
                $message = PHP_EOL . $body['url'];
                if (isset($data['send_confirmation']) && $data['send_confirmation']) {
                    $message .= PHP_EOL . $data['comment']['customer_note'];
                    $subject->getQuote()->addData(['customer_note' => $message, 'customer_note_notify' => true]);
                } elseif (isset($data['status'])) {
                    $historyComment = $this->orderStatusHistoryFactory->create();
                    $historyComment->setParentId($orderId);
                    $historyComment->setIsCustomerNotified(true);
                    $historyComment->setIsVisibleOnFront(true);
                    $historyComment->setComment($message);
                    $historyComment->setStatus($data['status']);
                    $this->orderManagement->addComment($orderId, $historyComment);
                }
            }
        }
    }

    /**
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
            'Authorization: Bearer ' . $token
        ];

        return [
            'headers' => $headers,
            'json' => $postData
        ];
    }
}
