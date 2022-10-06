<?php

declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Response;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class InitializeResponseHandler implements HandlerInterface
{
    /**
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDataObject = SubjectReader::readPayment($handlingSubject);

        $paymentInfo = $paymentDataObject->getPayment();

        // Do not let magento set status to processing, this will be handled once back from the redirect.
        $paymentInfo->setIsTransactionPending(true);
        // do not close transaction so you can do a cancel() and void
        $paymentInfo->setIsTransactionClosed(false);
        $paymentInfo->setShouldCloseParentTransaction(false);

        // Set the Rvvup Order ID as the transaction ID
        $paymentInfo->setTransactionId($response['data']['orderCreate']['id']);
        $paymentInfo->setCcTransId($response['data']['orderCreate']['id']);
        $paymentInfo->setLastTransId($response['data']['orderCreate']['id']);

        // Set data to the transaction
        $paymentInfo->setAdditionalInformation('rvvup_order_id', $response['data']['orderCreate']['id']);

        $paymentInfo->setAdditionalInformation('status', $response['data']['orderCreate']['status'] ?? null);
        $paymentInfo->setAdditionalInformation(
            'dashboardUrl',
            $response['data']['orderCreate']['dashboardUrl'] ?? null
        );

        $paymentActions = [];

        if (isset($response['data']['orderCreate']['paymentSummary']['paymentActions'])) {
            foreach ($response['data']['orderCreate']['paymentSummary']['paymentActions'] as $paymentAction) {
                $paymentActions[mb_strtolower($paymentAction['type'])] = [
                    mb_strtolower($paymentAction['method']) => $paymentAction['value'],
                ];
            }
        }

        // Add the payment actions (that include the redirect values).
        $paymentInfo->setAdditionalInformation('payment_actions', $paymentActions);

        // Don't send customer email.
        if (method_exists($paymentInfo, 'getOrder')) {
            $paymentInfo->getOrder()->setCanSendNewEmailFlag(false);
        }
    }
}
