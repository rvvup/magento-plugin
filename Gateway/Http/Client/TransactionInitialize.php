<?php

namespace Rvvup\Payments\Gateway\Http\Client;

use Exception;
use Magento\Payment\Gateway\Http\ClientException;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Rvvup\Payments\Model\SdkProxy;

class TransactionInitialize implements ClientInterface
{
    /**
     * @var \Rvvup\Payments\Model\SdkProxy
     */
    private $sdkProxy;

    /**
     * @param \Rvvup\Payments\Model\SdkProxy $sdkProxy
     * @return void
     */
    public function __construct(SdkProxy $sdkProxy)
    {
        $this->sdkProxy = $sdkProxy;
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
            throw new ClientException(__('%1', $ex->getMessage()));
        }
    }
}
