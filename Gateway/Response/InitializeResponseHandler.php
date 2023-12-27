<?php

declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Response;

use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\ResourceModel\Quote\Payment;
use Rvvup\Payments\Gateway\Method;

class InitializeResponseHandler implements HandlerInterface
{
    /**
     * @var \Magento\Framework\DataObjectFactory
     */
    private $dataObjectFactory;

    /** @var Payment */
    private $paymentResource;

    /**
     * @param DataObjectFactory $dataObjectFactory
     * @param Payment $paymentResource
     */
    public function __construct(
        DataObjectFactory $dataObjectFactory,
        Payment $paymentResource
    ) {
        $this->dataObjectFactory = $dataObjectFactory;
        $this->paymentResource = $paymentResource;
    }

    /**
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response): void
    {
        if (!isset($handlingSubject['quote'])) {
            return;
        }

        $payment = $handlingSubject['quote']->getPayment();

        $responseDataObject = $this->getResponseDataObject($response);

        $this->setPaymentAdditionalInformation($payment, $responseDataObject);
    }

    /**
     * Set the Payment's additional information.
     *
     * If it is an orderCreate with the express payment flag, then set additional information on unique key.
     * This will allow us to remove the data if the express payment method is cancelled or changed.
     * Otherwise, we have an orderCreate or orderUpdate call during checkout.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param \Magento\Framework\DataObject $responseDataObject
     * @return \Magento\Payment\Model\InfoInterface
     */
    private function setPaymentAdditionalInformation(
         $payment,
        DataObject $responseDataObject
    ): InfoInterface {
        $payment->setAdditionalInformation(Method::ORDER_ID, $responseDataObject->getData('id'));

        // Prepare & set the payment actions
        $paymentActions = [];
        $paymentSummary = $responseDataObject->getData('paymentSummary');

        if (is_array($paymentSummary)
            && isset($paymentSummary['paymentActions'])
            && is_array($paymentSummary['paymentActions'])
        ) {
            $paymentActions = $paymentSummary['paymentActions'];
        }

        // If this is a createOrder call for an express payment,
        // then set the data to separate key.
        if ($this->isExpressPayment($payment)) {
            $data = [
                'status' => $responseDataObject->getData('status'),
                'dashboardUrl' => $responseDataObject->getData('dashboardUrl'),
                'paymentActions' => $paymentActions
            ];

            $payment->setAdditionalInformation(Method::EXPRESS_PAYMENT_DATA_KEY, $data);

            return $payment;
        }

        // Otherwise, set normally.
        $payment->setAdditionalInformation('status', $responseDataObject->getData('status'));
        $payment->setAdditionalInformation(Method::DASHBOARD_URL, $responseDataObject->getData('dashboardUrl'));
        $payment->setAdditionalInformation('paymentActions', $paymentActions);
        $payment->setAdditionalInformation(Method::TRANSACTION_ID, $responseDataObject->getData('id'));
        $this->paymentResource->save($payment);

        return $payment;
    }

    /**
     * Generate a response data object from the response array.
     *
     * The response is either an orderExpressUpdate or an orderCreate.
     * This is already validated in the InitializeResponseValidator and also that the response is an array.
     * The response is either
     *
     * @param array $response
     * @return \Magento\Framework\DataObject
     */
    private function getResponseDataObject(array $response): DataObject
    {
        $responseDataObject = $this->dataObjectFactory->create();

        // Otherwise it will be an orderCreate.
        if (isset($response['data']['orderCreate'])) {
            $responseDataObject->setData($response['data']['orderCreate']);
        }

        if (isset($response['data']['orderUpdate'])) {
            $responseDataObject->setData($response['data']['orderUpdate']);
        }

        return $responseDataObject;
    }

    /**
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return bool
     */
    private function isExpressPayment(InfoInterface $payment): bool
    {
        return $payment->getAdditionalInformation(Method::EXPRESS_PAYMENT_KEY) === true;
    }
}
