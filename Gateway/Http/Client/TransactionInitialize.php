<?php

namespace Rvvup\Payments\Gateway\Http\Client;

use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
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
     * @var SessionManagerInterface
     */
    private $session;

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
     * @param SdkProxy $sdkProxy
     * @param LoggerInterface $logger
     * @param OrderDataBuilder $orderDataBuilder
     */
    public function __construct(
        SdkProxy $sdkProxy,
        LoggerInterface $logger,
        OrderDataBuilder $orderDataBuilder
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->logger = $logger;
        $this->orderDataBuilder = $orderDataBuilder;
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
        try {
            if (isset($transferObject->getBody()['id']) && isset($transferObject->getBody()['express'])) {
                $order = $this->sdkProxy->getOrder($transferObject->getBody()['id']);

                if ($order['status'] == Method::STATUS_EXPIRED) {
                    return $this->processExpiredOrder($order['externalReference']);
                }

                $input = $this->orderDataBuilder->createInputForExpressOrder($order['externalReference']);

                return $this->sdkProxy->updateOrder(['input' => $input]);
            }

            $quote = $this->session->getQuote();
            $input = $this->orderDataBuilder->build($quote);
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
        return $this->sdkProxy->createOrder(['input' => $input]);
    }
}
