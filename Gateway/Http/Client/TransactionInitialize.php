<?php

namespace Rvvup\Payments\Gateway\Http\Client;

use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Exception\QuoteValidationException;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\OrderDataBuilder;
use Rvvup\Payments\Model\SdkProxy;

class TransactionInitialize implements ClientInterface
{
    /**
     * @var \Rvvup\Payments\Model\SdkProxy
     */
    private $sdkProxy;

    /**
     * Set via di.xml
     *
     * @var LoggerInterface|RvvupLog
     */
    private $logger;

    /**
     * @var OrderDataBuilder
     */
    private $orderDataBuilder;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param SdkProxy $sdkProxy
     * @param LoggerInterface $logger
     * @param OrderDataBuilder $orderDataBuilder
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        SdkProxy $sdkProxy,
        LoggerInterface $logger,
        OrderDataBuilder $orderDataBuilder,
        StoreManagerInterface $storeManager
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->logger = $logger;
        $this->orderDataBuilder = $orderDataBuilder;
        $this->storeManager = $storeManager;
    }

    /**
     * Place the request via the API call.
     *
     * @param \Magento\Payment\Gateway\Http\TransferInterface $transferObject
     * @return array
     * @throws \Magento\Payment\Gateway\Http\ClientException
     */
    public function placeRequest(TransferInterface $transferObject): array
    {

        if (empty($transferObject->getBody())) {
            return [];
        }

        try {
            if (isset($transferObject->getBody()['id']) && isset($transferObject->getBody()['express'])) {
                $order = $this->sdkProxy->getOrder($transferObject->getBody()['id']);

                if ($order['status'] == Method::STATUS_EXPIRED) {
                    return $this->processExpiredOrder($order['externalReference']);
                }
                /** Remove express flag from body */
                $body = $transferObject->getBody();
                unset($body['express']);

                $input = $this->roundOrderValues($body);

                return $this->sdkProxy->updateOrder(['input' => $input]);
            }

            $input = $this->roundOrderValues($transferObject->getBody());

            $secureBaseUrl = $this->storeManager->getStore()->getBaseUrl(
                UrlInterface::URL_TYPE_WEB,
                true
            );
            $input['metadata']['domain'] = $secureBaseUrl;

            $order = $this->sdkProxy->createOrder(['input' => $input]);

            if ($order['data']['orderCreate']['status'] == Method::STATUS_EXPIRED) {
                return $this->processExpiredOrder($order['externalReference']);
            }

            return $order;
        } catch (Exception $ex) {
            $this->logger->error(
                sprintf('Error placing payment request, original exception %s', $ex->getMessage())
            );

            throw new ClientException(__('Something went wrong'));
        }
    }

    /**
     * @param string $orderId
     * @return array
     * @throws NoSuchEntityException
     * @throws QuoteValidationException
     */
    private function processExpiredOrder(string $orderId): array
    {
        $input = $this->orderDataBuilder->createInputForExpiredOrder($orderId);

        $secureBaseUrl = $this->storeManager->getStore()->getBaseUrl(
            UrlInterface::URL_TYPE_WEB,
            true
        );
        $input['metadata']['domain'] = $secureBaseUrl;

        return $this->sdkProxy->createOrder(['input' => $input]);
    }

    /**
     * @param array $body
     * @return array
     */
    private function roundOrderValues(array $body): array
    {
        // Round the order total values
        $body['total']['amount'] = $this->toCurrency($body['total']['amount']);
        $body['shippingTotal']['amount'] = $this->toCurrency($body['shippingTotal']['amount']);
        $body['discountTotal']['amount'] = $this->toCurrency($body['discountTotal']['amount']);
        $body['taxTotal']['amount'] = $this->toCurrency($body['taxTotal']['amount']);

        // Round the values for each item in the cart
        if (isset($body['items'])) {
            foreach ($body['items'] as $key => $item) {
                $body['items'][$key]['price']['amount'] = $this->toCurrency($item['price']['amount']);
                $body['items'][$key]['priceWithTax']['amount'] = $this->toCurrency($item['priceWithTax']['amount']);
                $body['items'][$key]['tax']['amount'] = $this->toCurrency($item['tax']['amount']);
                $body['items'][$key]['total']['amount'] = $this->toCurrency($item['total']['amount']);
            }
        }

        // Return order input with rounded figures
        return $body;
    }

    /**
     * @param string $amount
     * @return string
     */
    private function toCurrency(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
