<?php
declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\InvoiceRepository;
use Magento\Sales\Model\Order\Payment;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\SdkProxy;
use Rvvup\Payments\Service\Cache;

class VoidPayment implements CommandInterface
{
    /** @var SdkProxy */
    private $sdkProxy;

    /** @var InvoiceRepository */
    private $invoiceRepository;

    /**
     * @var LoggerInterface|RvvupLog
     */
    private $logger;

    /** @var Cache */
    private $cache;

    /**
     * @param SdkProxy $sdkProxy
     * @param InvoiceRepository $invoiceRepository
     * @param Cache $cache
     * @param LoggerInterface $logger
     */
    public function __construct(
        SdkProxy $sdkProxy,
        InvoiceRepository $invoiceRepository,
        Cache $cache,
        LoggerInterface $logger
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->invoiceRepository = $invoiceRepository;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * @param array $commandSubject
     * @return void
     * @throws LocalizedException
     */
    public function execute(array $commandSubject)
    {
        try {
            $payment = $commandSubject['payment']->getPayment();

            list($rvvupOrderId, $paymentId) = $this->getRvvupData($payment);
            $this->sdkProxy->voidPayment($rvvupOrderId, $paymentId);

            $orderState = $payment->getOrder()->getState();
            $this->cache->clear($rvvupOrderId, $orderState);

            $order = $payment->getOrder();
            $this->disableOnlineRefunds($order);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new LocalizedException(__('Something went wrong when trying to void a Rvvup payment'));
        }
    }

    /**
     * @param Payment $payment
     * @return array
     */
    private function getRvvupData(Payment $payment)
    {
        $rvvupOrderId = $payment->getAdditionalInformation(Method::ORDER_ID);
        $rvvupOrder = $this->sdkProxy->getOrder($rvvupOrderId);
        $paymentId = $rvvupOrder['payments'][0]['id'];
        return [$rvvupOrderId, $paymentId];
    }

    /**
     * @param OrderInterface $order
     * @return void
     */
    private function disableOnlineRefunds(OrderInterface $order)
    {
        foreach ($order->getInvoiceCollection()->getItems() as $invoice) {
            if ($invoice->getTransactionId()) {
                $invoice->setTransactionId(null);
                $this->invoiceRepository->save($invoice);
            }
        }
    }
}
