<?php declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Model\Order\Payment;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\SdkProxy;

class FetchUpdate extends AbstractCommand implements CommandInterface
{
    /**
     * @param array $commandSubject
     * @return \Magento\Payment\Gateway\Command\ResultInterface|void|null
     */
    public function execute(array $commandSubject)
    {
        /** @var Payment $payment */
        $payment = $commandSubject['payment']->getPayment();
        $paymentId = $payment->getAdditionalInformation('id');
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
        $payment->setIsTransactionApproved(true);
    }

    /**
     * @param Payment $payment
     * @return void
     * phpcs:disable Magento2.CodeAnalysis.EmptyBlock.DetectedFunction
     */
    private function defer(Payment $payment): void
    {
    }
    // phpcs:enable Magento2.CodeAnalysis.EmptyBlock.DetectedFunction

    /**
     * @param Payment $payment
     * @return mixed
     * @throws CommandException
     */
    private function decline(Payment $payment)
    {
        $payment->setIsTransactionDenied(true);
    }
}
