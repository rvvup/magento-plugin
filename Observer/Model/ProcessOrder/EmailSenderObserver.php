<?php

declare(strict_types=1);

namespace Rvvup\Payments\Observer\Model\ProcessOrder;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\Logger;
use Throwable;

class EmailSenderObserver implements ObserverInterface
{
    /**
     * @var \Magento\Sales\Api\InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    private $invoiceSender;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    private $orderSender;

    /**
     * Set via di.xml
     *
     * @var \Psr\Log\LoggerInterface|Logger
     */
    private $logger;

    /**
     * @param \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepository
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    public function __construct(
        InvoiceRepositoryInterface $invoiceRepository,
        OrderRepositoryInterface $orderRepository,
        InvoiceSender $invoiceSender,
        OrderSender $orderSender,
        LoggerInterface $logger
    ) {
        $this->invoiceRepository = $invoiceRepository;
        $this->orderRepository = $orderRepository;
        $this->invoiceSender = $invoiceSender;
        $this->orderSender = $orderSender;
        $this->logger = $logger;
    }

    /**
     * Send order confirmation & invoice emails.
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $orderId = $observer->getData('order_id');
        $invoiceId = $observer->getData('invoice_id');

        if ($orderId !== null) {
            $this->sendOrderConfirmationEmail((int) $orderId);
        }

        if ($invoiceId !== null) {
            $this->sendInvoiceEmail((int) $invoiceId);
        }
    }

    /**
     * @param int $orderId
     * @return void
     */
    private function sendOrderConfirmationEmail(int $orderId): void
    {
        try {
            $order = $this->orderRepository->get($orderId);

            // No action if email already sent.
            if ((bool) $order->getEmailSent()) {
                return;
            }

            $this->orderSender->send($order);
        } catch (Throwable $t) {
            // General catch of errors not caught by orderSender
            $this->logger->addError(
                'Error thrown on sending Rvvup order confirmation email with message: ',
                [
                    'magento' => ['order_id' => $orderId],
                    'cause' => $t->getMessage()
                ]
            );
        }
    }

    /**
     * @param int $invoiceId
     * @return void
     */
    private function sendInvoiceEmail(int $invoiceId): void
    {
        try {
            $invoice = $this->invoiceRepository->get($invoiceId);

            // No action if email already sent.
            if ((bool) $invoice->getEmailSent()) {
                return;
            }

            $this->invoiceSender->send($invoice);
        } catch (Throwable $t) {
            // General catch of errors not caught by orderSender
            $this->logger->addError(
                'Error thrown on sending Rvvup order Invoice email with message: ',
                [
                    'invoice_id' => $invoiceId,
                    'cause' => $t->getMessage()
                ]
            );
        }
    }
}
