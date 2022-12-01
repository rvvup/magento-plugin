<?php

namespace Rvvup\Payments\Gateway\Http\Client;

use Exception;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Psr\Log\LoggerInterface;
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
     * @param SdkProxy $sdkProxy
     * @param LoggerInterface $logger
     */
    public function __construct(
        SdkProxy $sdkProxy,
        LoggerInterface $logger
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->logger = $logger;
    }

    /**
     * @param \Magento\Payment\Gateway\Http\TransferInterface $transferObject
     * @return array
     * @throws \Magento\Payment\Gateway\Http\ClientException
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        try {
            return $this->sdkProxy->createOrder(['input' => $transferObject->getBody()]);
        } catch (Exception $ex) {
            $this->logger->error(
                sprintf('Error placing payment request, original exception %s', $ex->getMessage())
            );

            throw new ClientException(__('Something went wrong'));
        }
    }
}
