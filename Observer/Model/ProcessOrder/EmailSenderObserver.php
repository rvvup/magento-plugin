<?php

declare(strict_types=1);

namespace Rvvup\Payments\Observer\Model\ProcessOrder;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Psr\Log\LoggerInterface;
use Throwable;

class EmailSenderObserver implements ObserverInterface
{
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    private $orderSender;

    /**
     * Set via di.xml
     *
     * @var \Psr\Log\LoggerInterface|RvvupLog
     */
    private $logger;

    /**
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderSender $orderSender,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderSender = $orderSender;
        $this->logger = $logger;
    }

    /**
     * Send order confirmation email.
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $orderId = $observer->getData('order_id');

        // Verify all data exist.
        if ($orderId === null) {
            return;
        }

        try {
            $order = $this->orderRepository->get($orderId);

            // No action if email already sent.
            if ((bool) $order->getEmailSent()) {
                return;
            }

            $this->orderSender->send($order);
        } catch (Throwable $t) {
            // General catch of errors not caught by orderSender
            $this->logger->error(
                'Error thrown while sending order confirmation email for rvvup order: ' . $t->getMessage(),
                [
                    'order_id' => $orderId,
                ]
            );
        }
    }
}
