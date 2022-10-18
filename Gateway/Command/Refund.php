<?php declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Rvvup\Payments\Model\SdkProxy;

class Refund implements CommandInterface
{
    /** @var SdkProxy */
    private $sdkProxy;

    /**
     * @param SdkProxy $sdkProxy
     */
    public function __construct(
        SdkProxy $sdkProxy
    ) {
        $this->sdkProxy = $sdkProxy;
    }

    public function execute(array $commandSubject)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $commandSubject['payment']->getPayment();
        $idempotencyKey = $payment->getTransactionId() . '-' . time();

        $result = $this->sdkProxy->refundOrder(
            $payment->getParentTransactionId(),
            $commandSubject['amount'],
            $payment->getCreditMemo() !== null ? $payment->getCreditMemo()->getCustomerNote() : "",
            $idempotencyKey
        );
        // The latest refund ID is always the one closest to the top
        $refund = $result["payments"][0]["refunds"][0];
        $refundId = $refund['id'];
        $refundStatus = $refund['status'];
        switch ($refundStatus) {
            case 'SUCCEEDED':
                $payment->setTransactionId($refundId);
                break;

            case 'PENDING':
                $payment->setTransactionId($refundId);
                $payment->setIsTransactionPending(true);
                break;

            case 'FAILED':
                throw new CommandException(__("There was an error whilst refunding"));
        }
        return $this;
    }
}
