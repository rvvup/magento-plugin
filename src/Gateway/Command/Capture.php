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

class Capture implements CommandInterface
{

    /** @var SdkProxy */
    private $sdkProxy;
    /** @var \Rvvup\Payments\Service\Capture */
    private $captureService;
    /** @var Logger */
    private $logger;

    /**
     * @param SdkProxy $sdkProxy
     * @param \Rvvup\Payments\Service\Capture $captureService
     * @param Logger $logger
     */
    public function __construct(
        SdkProxy $sdkProxy,
        \Rvvup\Payments\Service\Capture $captureService,
        Logger   $logger
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->captureService = $captureService;
        $this->logger = $logger;
    }

    /**
     * @param array $commandSubject
     * @return void
     * @throws Exception
     */
    public function execute(array $commandSubject)
    {
        /** @var Payment $payment */
        $payment = $commandSubject['payment']->getPayment();
        $order = $payment->getOrder();
        $storeId = (string) $order->getStoreId();
        $rvvupOrderId = $payment->getAdditionalInformation(Method::ORDER_ID);
        if (!$rvvupOrderId) {
            $this->logger->addRvvupError(
                'Cannot capture online without rvvup order id',
                null,
                null,
                null,
                $order->getId()
            );
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
                $captureSucceeded = $this->captureService->paymentCapture(
                    $rvvupOrderId,
                    $rvvupPaymentId,
                    'invoice',
                    $storeId
                );
                if (!$captureSucceeded) {
                    throw new CommandException(__("Error when trying to capture the payment, please try again."));
                }
                break;
            case 'CANCELLED':
            case 'DECLINED':
            default:
                throw new CommandException(__('Sorry, the payment was declined, please try another method'));
        }
    }
}
