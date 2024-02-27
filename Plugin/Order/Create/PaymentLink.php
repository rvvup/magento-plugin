<?php

namespace Rvvup\Payments\Plugin\Order\Create;

use Laminas\Http\Request;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\AdminOrder\Create;
use Magento\Store\Model\ScopeInterface;
use Rvvup\Payments\Model\Config;
use Rvvup\Payments\Model\RvvupConfigProvider;
use Rvvup\Payments\Sdk\Curl;

class PaymentLink
{
    /** @var Curl */
    private Curl $curl;

    /** @var Config */
    private $config;

    /** @var SerializerInterface  */
    private $json;

    public function __construct(
        Curl                               $curl,
        Config                             $config,
        SerializerInterface                $json
    ) {
        $this->curl = $curl;
        $this->config = $config;
        $this->json = $json;
    }

    /**
     * @param Create $subject
     * @param $result
     * @param array $data
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function afterImportPostData(Create $subject, $result, array $data)
    {
        if ($result->getQuote() && $result->getQuote()->getPayment()->getMethod() == RvvupConfigProvider::CODE
        && $data['send_confirmation']) {
            $this->createRvvupPayByLink($result->getQuote(), $subject);
        }

        return $result;
    }

    /**
     * Create Rvvup pay-by-link and save it to comment
     * @param CartInterface $quote
     * @param Create $subject
     * @return void
     * @throws NoSuchEntityException
     */
    private function createRvvupPayByLink(CartInterface $quote, Create $subject): void
    {
        $storeId = (string)$quote->getStore()->getId();
        $amount = number_format((float)$quote->getGrandTotal(), 2, '.', '');
        $params = $this->getData($amount, $storeId, $quote);

        $request = $this->curl->request(Request::METHOD_POST, $this->getApiUrl($storeId), $params);
        $body = $this->json->unserialize($request->body);
        $this->processApiResponse($body, $amount, $subject);
    }

    /**
     * @param array $body
     * @param string $amount
     * @param Create $subject
     * @return void
     */
    private function processApiResponse(array $body, string $amount, Create $subject): void
    {
        if ($body['status'] == 'ACTIVE') {
            if ($amount == $body['amount']['amount']) {
                $message = 'This order requires payment, please pay using following link:'. PHP_EOL . $body['url'];
                $subject->getQuote()->addData(['customer_note' => $message, 'customer_note_notify' => true]);
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
    private function getData(string $amount, string $storeId, CartInterface $cart): array
    {
        $postData = [
            'amount' => ['amount' => $amount, 'currency' => $cart->getQuoteCurrencyCode()],
            'reference' => $cart->getReservedOrderId(),
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
