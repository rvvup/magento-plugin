<?php

declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Exception;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Model\Order\Payment;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Logger;
use Rvvup\Payments\Model\SdkProxy;
use Rvvup\Sdk\Exceptions\NetworkException;

class Capture implements CommandInterface
{

    /** @var SdkProxy */
    protected $sdkProxy;
    /** @var Logger */
    protected $logger;

    /**
     * @param SdkProxy $sdkProxy
     * @param Logger $logger
     */
    public function __construct(
        SdkProxy $sdkProxy,
        Logger   $logger
    )
    {
        $this->sdkProxy = $sdkProxy;
        $this->logger = $logger;
    }

    /**
     * @param array $commandSubject
     * @return void
     * @throws Exception
     */
    public function execute(array $commandSubject)
    {
        $this->logger->error('Capture command executed');
        /** @var Payment $payment */
        $payment = $commandSubject['payment']->getPayment();
        $order = $payment->getOrder();
        $storeId = (string)$order->getStoreId();
        $rvvupOrderId = $payment->getAdditionalInformation(Method::ORDER_ID);
        if (!$rvvupOrderId) {
            $this->logger->addRvvupError('Cannot capture online without rvvup order id', null, null, null, $order->getId());
            throw new CommandException(__("Payment cannot be captured because it hasn't been authorised yet."));
        }
        $rvvupOrder = $this->sdkProxy->getOrder($rvvupOrderId, $storeId);
        $rvvupPaymentId = $rvvupOrder['payments'][0]['id'];

        switch ($rvvupOrder['payments'][0]['status']) {
            case 'REQUIRES_ACTION':
            case 'PENDING':
                $payment->setIsTransactionPending(true);
                break;
            case 'SUCCEEDED':
            case 'AUTHORIZED':
                $this->success((string)$order->getStoreId(), $rvvupOrderId, $rvvupPaymentId, $payment);
                break;
            case 'CANCELLED':
            case 'DECLINED':
            default:
                throw new CommandException(__('Sorry, the payment was declined, please try another method'));
        }
    }

    /**
     * @param string $storeId
     * @param string $rvvupOrderId
     * @param string $rvvupPaymentId
     * @param Payment $payment
     * @return void
     * @throws NetworkException
     * @throws \JsonException
     */
    private function success(string $storeId, string $rvvupOrderId, string $rvvupPaymentId, Payment $payment): void
    {
        $this->logger->error('exec success');

        $this->sdkProxy->paymentCapture($rvvupOrderId, $rvvupPaymentId, $storeId);
    }
}
