<?php
declare(strict_types=1);

namespace Rvvup\Payments\Controller\Adminhtml\Create;

use Laminas\Http\Request;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\ResourceModel\Quote\Payment;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Rvvup\Payments\Model\Config;
use Rvvup\Payments\Sdk\Curl;

class VirtualTerminal extends Action implements HttpPostActionInterface
{

    /** @var JsonFactory */
    private $resultJsonFactory;

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

    /** @var UrlInterface */
    private $url;

    /** @var CartRepositoryInterface */
    private $cartRepository;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param Curl $curl
     * @param Config $config
     * @param SerializerInterface $json
     * @param OrderRepositoryInterface $orderRepository
     * @param CartRepositoryInterface $cartRepository
     * @param Payment $paymentResource
     * @param UrlInterface $url
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Curl $curl,
        Config $config,
        SerializerInterface $json,
        OrderRepositoryInterface $orderRepository,
        CartRepositoryInterface $cartRepository,
        Payment $paymentResource,
        UrlInterface $url
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->json = $json;
        $this->config = $config;
        $this->orderRepository = $orderRepository;
        $this->paymentResource = $paymentResource;
        $this->curl = $curl;
        $this->url = $url;
        $this->cartRepository = $cartRepository;
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $amount = $this->_request->getParam('amount');
        $storeId = $this->_request->getParam('store_id');
        $orderId = $this->_request->getParam('order_id');
        $currencyCode = $this->_request->getParam('currency_code');
        $result = $this->resultJsonFactory->create();

        try {
            $params = $this->getData($amount, $storeId, $orderId, $currencyCode);
            $request = $this->curl->request(Request::METHOD_POST, $this->getApiUrl($storeId), $params);
            $body = $this->json->unserialize($request->body);
            $this->processResponse($orderId, $body);
        } catch (\Exception $e) {
            /** @todo add logging */
            $result->setData([
                'success' => false
            ]);
            return $result;
        }

        $result->setData([
            'iframe-url' => $body['url'],
            'success' => true
        ]);

        return $result;
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
            $quoteId = $order->getQuoteId();
            $quote = $this->cartRepository->get($quoteId);
            $payment = $quote->getPayment();
            $payment->setAdditionalInformation('rvvup_moto_id', $motoId);
            $this->paymentResource->save($payment);
        } catch (\Exception $e) {
            //@todo add logging
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

    private function getData(string $amount, string $storeId, string $orderId, string $currencyCode): array
    {
        $url = $this->url->getBaseUrl(['_scope' => $storeId])
            . "rvvup/redirect/in?store_id=$storeId&checkout_id={{CHECKOUT_ID}}";

        $postData = [
            'amount' => ['amount' => $amount, 'currency' => $currencyCode],
            'reference' => $orderId,
            'source' => 'MAGENTO_MOTO',
            'successUrl' => $url
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
