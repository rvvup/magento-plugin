<?php
declare(strict_types=1);

namespace Rvvup\Payments\Plugin;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterfaceFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Store\Model\ScopeInterface;
use Rvvup\Payments\Model\Config;
use Rvvup\Payments\Model\RvvupConfigProvider;
use Rvvup\Payments\Sdk\Curl;
use Laminas\Http\Request;

class PaymentLink
{
    /** @var Curl */
    private Curl $curl;

    /** @var Config */
    private $config;

    /** @var OrderStatusHistoryInterfaceFactory  */
    private $orderStatusHistoryFactory;

    /** @var OrderManagementInterface  */
    private $orderManagement;

    /** @var SerializerInterface  */
    private $json;

    /**
     * @param Curl $curl
     * @param Config $config
     * @param OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory
     * @param SerializerInterface $json
     * @param OrderManagementInterface $orderManagement
     */
    public function __construct(
        Curl                               $curl,
        Config                             $config,
        OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory,
        SerializerInterface                $json,
        OrderManagementInterface           $orderManagement
    ) {
        $this->curl = $curl;
        $this->config = $config;
        $this->orderStatusHistoryFactory = $orderStatusHistoryFactory;
        $this->json = $json;
        $this->orderManagement = $orderManagement;
    }

    /**
     * @param QuoteManagement $subject
     * @param AbstractExtensibleModel $result
     * @param Quote $quote
     * @param array $orderData
     * @return AbstractExtensibleModel
     * @throws NoSuchEntityException
     */
    public function afterSubmit(
        QuoteManagement         $subject,
        AbstractExtensibleModel $result,
        Quote                   $quote,
        array                   $orderData = []
    ): AbstractExtensibleModel {
        if ($result->getPayment() && $result->getPayment()->getMethod() == RvvupConfigProvider::CODE) {
            $this->createRvvupPayByLink($result);
        }
        return $result;
    }

    /**
     * Create Rvvup pay-by-link and save it to comment
     * @param OrderInterface $order
     * @return void
     * @throws NoSuchEntityException
     */
    private function createRvvupPayByLink(OrderInterface $order): void
    {
        $storeId = (string)$order->getStore()->getId();
        $amount = number_format((float)$order->getGrandTotal(), 2, '.', '');
        $params = $this->getData($amount, $storeId, $order);

        $request = $this->curl->request(Request::METHOD_POST, $this->getApiUrl($storeId), $params);
        $body = $this->json->unserialize($request->body);
        $this->processApiResponse($body, $order, $amount);
    }

    /**
     * @param array $body
     * @param OrderInterface $order
     * @param string $amount
     * @return void
     */
    private function processApiResponse(array $body, OrderInterface $order, string $amount): void
    {
        if ($body['status'] == 'ACTIVE') {
            if ($amount == $body['amount']['amount']) {
                $historyComment = $this->orderStatusHistoryFactory->create();
                $historyComment->setParentId($order->getEntityId());
                $historyComment->setIsCustomerNotified(true);
                $historyComment->setIsVisibleOnFront(true);
                $historyComment->setStatus($order->getStatus());
                $historyComment->setComment('Rvvup paylink was generated, please pay using following link:'.
                    PHP_EOL . 'https://checkout.dev.rvvuptech.com/l/' . $body['id']);
                $this->orderManagement->addComment($order->getEntityId(), $historyComment);
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
        $baseUrl = str_replace('graphql', 'api/v1', $baseUrl);

        return "$baseUrl/$merchantId/payment-links";
    }

    /**
     * @param string $amount
     * @param string $storeId
     * @param OrderInterface $order
     * @return array
     * @throws NoSuchEntityException
     */
    private function getData(string $amount, string $storeId, OrderInterface $order): array
    {
        $postData = [
            'amount' => ['amount' => $amount, 'currency' => $order->getOrderCurrencyCode()],
            'reference' => $order->getId(),
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
