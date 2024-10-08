<?php

declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\RvvupConfigProvider;
use Rvvup\Sdk\Exceptions\NetworkException;

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
        /** @var Order $order */
        $order = $payment->getOrder();
        $storeId = (string)$order->getStoreId();
        $paymentId = $payment->getAdditionalInformation(Method::ORDER_ID);
        $rvvupOrder = $this->sdkProxy->getOrder($paymentId, $storeId);
        $state = self::STATE_MAP[$rvvupOrder['payments'][0]['status']] ?? 'decline';
        $this->$state($payment);
    }

    /**
     * @param Payment $payment
     * @return void
     * @throws NetworkException
     */
    private function success(Payment $payment): void
    {
        /** @var Order $order */
        $order = $payment->getOrder();
        $storeId = (string)$order->getStoreId();
        $rvvupOrderId = $payment->getAdditionalInformation(Method::ORDER_ID);
        $rvvupPaymentId = $payment->getAdditionalInformation(Method::PAYMENT_ID);
        $this->sdkProxy->paymentCapture($rvvupOrderId, $rvvupPaymentId, $storeId);
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
