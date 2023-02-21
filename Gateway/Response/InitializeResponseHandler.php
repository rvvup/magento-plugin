<?php

declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Response;

use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Payment\Model\InfoInterface;
use Rvvup\Payments\Gateway\Method;

class InitializeResponseHandler implements HandlerInterface
{
    /**
     * @var \Magento\Framework\DataObjectFactory
     */
    private $dataObjectFactory;

    /**
     * Flag property key to identify whether the response is for an orderExpressUpdate call.
     *
     * @var string
     */
    private $orderExpressUpdateFlag = 'is_order_express_update_flag';

    /**
     * @param \Magento\Framework\DataObjectFactory $dataObjectFactory
     * @return void
     */
    public function __construct(DataObjectFactory $dataObjectFactory)
    {
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $payment = SubjectReader::readPayment($handlingSubject)->getPayment();

        $responseDataObject = $this->getResponseDataObject($response);

        $payment = $this->setPaymentAdditionalInformation($payment, $responseDataObject);

        // If the payment method instance is not an Order Payment, no further actions.
        // In that scenario, it is a Quote Payment instance for an Express Create.
        if (!method_exists($payment, 'getOrder')) {
            return;
        }

        // Do not let magento set status to processing, this will be handled once back from the redirect.
        $payment->setIsTransactionPending(true);
        // do not close transaction, so you can do a cancel() and void
        $payment->setIsTransactionClosed(false);
        $payment->setShouldCloseParentTransaction(false);

        // Set the Rvvup Order ID as the transaction ID
        $payment->setTransactionId($responseDataObject->getData('id'));
        $payment->setCcTransId($responseDataObject->getData('id'));
        $payment->setLastTransId($responseDataObject->getData('id'));

        // Don't send customer email.
        $payment->getOrder()->setCanSendNewEmailFlag(false);
    }

    /**
     * Set the Payment's additional information.
     *
     * If it is an orderCreate with the express payment flag, then set additional information on unique key.
     * This will allow us to remove the data if the express payment method is cancelled or changed.
     * Otherwise, we have an orderCreate or orderExpressUpdate call during checkout.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param \Magento\Framework\DataObject $responseDataObject
     * @return \Magento\Payment\Model\InfoInterface
     */
    private function setPaymentAdditionalInformation(
        InfoInterface $payment,
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
        if ($this->isExpressPayment($payment) && $responseDataObject->getData($this->orderExpressUpdateFlag) !== true) {
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
        $payment->setAdditionalInformation('dashboardUrl', $responseDataObject->getData('dashboardUrl'));
        $payment->setAdditionalInformation('paymentActions', $paymentActions);

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

        // If orderExpressUpdate, set data & flag and return.
        if (isset($response['data']['orderExpressUpdate'])) {
            $responseDataObject->setData($response['data']['orderExpressUpdate']);
            $responseDataObject->setData($this->orderExpressUpdateFlag, true);

            return $responseDataObject;

        }

        // Otherwise it will be an orderCreate.
        $responseDataObject->setData($response['data']['orderCreate']);

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
