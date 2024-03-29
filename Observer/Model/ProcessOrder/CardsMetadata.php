<?php

declare(strict_types=1);

namespace Rvvup\Payments\Observer\Model\ProcessOrder;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterfaceFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class CardsMetadata implements ObserverInterface
{
    /** @var OrderRepositoryInterface $orderRepository */
    private $orderRepository;

    /** @var LoggerInterface $logger */
    private $logger;

    /** @var OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory */
    private $orderStatusHistoryFactory;

    /** @var OrderManagementInterface $orderManagement */
    private $orderManagement;

    /**
     * @param OrderRepositoryInterface           $orderRepository
     * @param OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory
     * @param OrderManagementInterface           $orderManagement
     * @param LoggerInterface                    $logger
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory,
        OrderManagementInterface $orderManagement,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderStatusHistoryFactory = $orderStatusHistoryFactory;
        $this->orderManagement = $orderManagement;
        $this->logger = $logger;
    }

    /**
     * Send order confirmation & invoice emails.
     *
     * @param  Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $orderId = $observer->getData('order_id');
        $rvvupData = $observer->getData('rvvup_data');
        $payment = $rvvupData['payments'][0];
        $order = $this->orderRepository->get($orderId);
        if ($order->getPayment()->getMethod() == 'rvvup_CARD') {
            $data = [];
            $keys = [
                'cvvResponseCode',
                'avsAddressResponseCode',
                'avsPostCodeResponseCode',
                'eci',
                'cavv',
                'acquirerResponseCode',
                'acquirerResponseMessage'
            ];

            foreach ($keys as $key) {
                $this->populateCardData($data, $payment, $key);
            }

            if (!empty($data)) {
                try {
                    $historyComment = $this->orderStatusHistoryFactory->create();
                    $historyComment->setParentId($order->getEntityId());
                    $historyComment->setIsCustomerNotified(0);
                    $historyComment->setIsVisibleOnFront(0);
                    $historyComment->setStatus($order->getStatus());
                    $status = nl2br("Rvvup payment status " . $payment['status'] . "\n card data: \n");
                    $message = __($status . nl2br(implode("\n", $data)));
                    $historyComment->setComment($message);
                    $this->orderManagement->addComment($order->getEntityId(), $historyComment);
                } catch (\Exception $e) {
                    $this->logger->error('Rvvup cards metadata comment fails with exception: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * @param array $data
     * @param array $payment
     * @param string $key
     * @return void
     */
    private function populateCardData(array &$data, array $payment, string $key): void
    {
        if (isset($payment[$key])) {
            $value = $this->mapCardValue($payment[$key]);
            $data[$key] = $key . ': ' . $value;
        }
    }

    /**
     * @param string $value
     * @return string
     */
    private function mapCardValue(string $value): string
    {
        switch ($value) {
            case "0":
                if ($value !== '0') {
                    return $value;
                }
                return '0 - Not Given';

            case "1":
                return '1 - Not Checked';

            case "2":
                return '2 - Match';

            case "4":
                return '4 - Not Matched';

            default:
                return $value;
        }
    }
}
