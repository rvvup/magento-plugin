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
     * Place the request via the API call.
     *
     * If `is_rvvup_express_payment_update` param is provided in the request data,
     * perform express update call to complete the payment.
     *
     * @param \Magento\Payment\Gateway\Http\TransferInterface $transferObject
     * @return array
     * @throws \Magento\Payment\Gateway\Http\ClientException
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        try {
            $requestBody = $transferObject->getBody();

            if (isset($requestBody['is_rvvup_express_payment_update'])
                && $requestBody['is_rvvup_express_payment_update'] === true
            ) {
                $requestBody = $this->limitExpressPaymentUpdateRequestData($requestBody);

                return $this->sdkProxy->updateExpressOrder(['input' => $requestBody]);
            }

            // Otherwise standard order payment.
            return $this->sdkProxy->createOrder(['input' => $requestBody]);
        } catch (Exception $ex) {
            $this->logger->error(
                sprintf('Error placing payment request, original exception %s', $ex->getMessage())
            );

            throw new ClientException(__('Something went wrong'));
        }
    }

    /**
     * Remove any request fields that are not allowed for a payment express update request.
     *
     * ToDo: Refactor Order Builder so data can be built differently depending the request type.
     *
     * @param array $requestBody
     * @return array
     */
    private function limitExpressPaymentUpdateRequestData(array $requestBody): array
    {
        // Remove not required key values
        unset(
            $requestBody['type'],
            $requestBody['is_rvvup_express_payment_update'],
            $requestBody['redirectToStoreUrl'],
            $requestBody['items'],
            $requestBody['requiresShipping'],
            $requestBody['method']
        );

        return $requestBody;
    }
}
