<?php declare(strict_types=1);

namespace Rvvup\Payments\Observer\Model\ProcessOrder;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterfaceFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\Logger;

class AddOrderHistoryCommentObserver implements ObserverInterface
{
    /**
     * @var \Magento\Sales\Api\Data\OrderStatusHistoryInterfaceFactory
     */
    private $orderStatusHistoryFactory;

    /**
     * @var \Magento\Sales\Api\OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * Set via di.xml
     *
     * @var \Psr\Log\LoggerInterface|Logger
     */
    private $logger;

    /**
     * @param \Magento\Sales\Api\Data\OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory
     * @param \Magento\Sales\Api\OrderManagementInterface $orderManagement
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    public function __construct(
        OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory,
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->orderStatusHistoryFactory = $orderStatusHistoryFactory;
        $this->orderManagement = $orderManagement;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
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

            $this->addEventMessageComment($observer, $order);
            $this->addOrderStateChangeComment($observer, $order);
            $this->addOrderStatusChangeComment($observer, $order);
        } catch (Exception $e) {
            $this->logger->error(
                'Error saving Rvvup Order Payment Processor comment with message: ' . $e->getMessage(),
                [
                    'order_id' => $orderId,
                ]
            );
        }
    }

    /**
     * Add status history with event message.
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return void
     */
    private function addEventMessageComment(Observer $observer, OrderInterface $order): void
    {
        $eventMessage = $observer->getData('event_message');
        $rvvupId = $observer->getData('rvvup_id');

        // Prepare comment.
        $comment = is_string($eventMessage) ? trim($eventMessage) : 'Rvvup Order Process was performed.';
        $comment .= $rvvupId !== null ? ' Rvvup Order ID: ' . $rvvupId : ' Rvvup Payment ID: N/A';

        $orderStatusHistory = $this->createNewOrderStatusHistoryObject($order);
        $orderStatusHistory->setComment($comment);
        $this->orderManagement->addComment($order->getEntityId(), $orderStatusHistory);
    }

    /**
     * Add status history if order state has changed.
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return void
     */
    private function addOrderStateChangeComment(Observer $observer, OrderInterface $order): void
    {
        $originalOrderState = $observer->getData('original_order_state');

        // If no data or no chage, no comment in history
        if ($originalOrderState === null || $originalOrderState === $order->getState()) {
            return;
        }

        $orderStatusHistory = $this->createNewOrderStatusHistoryObject($order);
        $orderStatusHistory->setComment(
            sprintf('Rvvup Order State changed from `%s` to `%s`.', $originalOrderState, $order->getState())
        );
        $this->orderManagement->addComment($order->getEntityId(), $orderStatusHistory);
    }

    /**
     * Add status history if order status has changed.
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return void
     */
    private function addOrderStatusChangeComment(Observer $observer, OrderInterface $order): void
    {
        $originalOrderStatus = $observer->getData('original_order_status');

        // If no data or no change, no comment in history
        if ($originalOrderStatus === null || $originalOrderStatus === $order->getStatus()) {
            return;
        }

        $orderStatusHistory = $this->createNewOrderStatusHistoryObject($order);
        $orderStatusHistory->setComment(
            sprintf('Rvvup Order Status changed from `%s` to `%s`.', $originalOrderStatus, $order->getStatus())
        );
        $this->orderManagement->addComment($order->getEntityId(), $orderStatusHistory);
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return \Magento\Sales\Api\Data\OrderStatusHistoryInterface
     */
    private function createNewOrderStatusHistoryObject(OrderInterface $order): OrderStatusHistoryInterface
    {
        /** @var OrderStatusHistoryInterface $historyComment */
        $historyComment = $this->orderStatusHistoryFactory->create();
        $historyComment->setParentId($order->getEntityId());
        $historyComment->setIsCustomerNotified(0);
        $historyComment->setIsVisibleOnFront(0);
        $historyComment->setStatus($order->getStatus());

        return $historyComment;
    }
}
