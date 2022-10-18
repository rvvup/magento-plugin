<?php

declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Model\Order\Payment;

class Capture extends AbstractCommand implements CommandInterface
{
    /**
     * @param array $commandSubject
     * @return \Magento\Payment\Gateway\Command\ResultInterface|void|null
     */
    public function execute(array $commandSubject)
    {
        /** @var Payment $payment */
        $payment = $commandSubject['payment']->getPayment();
        $paymentId = $payment->getAdditionalInformation('rvvup_order_id');
        $rvvupOrder = $this->sdkProxy->getOrder($paymentId);
        $state = self::STATE_MAP[$rvvupOrder['status']] ?? 'decline';
        $this->$state($payment);
    }

    /**
     * @param Payment $payment
     * @return void
     */
    private function success(Payment $payment): void
    {
        $rvvupOrderId = $payment->getAdditionalInformation('rvvup_order_id');
        $payment->setTransactionId($rvvupOrderId);
    }

    /**
     * @param Payment $payment
     * @return void
     */
    private function defer(Payment $payment): void
    {
        $payment->setIsTransactionPending(true);
    }

    /**
     * @param Payment $payment
     * @return mixed
     * @throws CommandException
     */
    private function decline(Payment $payment)
    {
        throw new CommandException(__('Sorry, the payment was declined, please try another method'));
    }
}
