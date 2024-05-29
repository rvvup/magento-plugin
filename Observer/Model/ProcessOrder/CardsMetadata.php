<?php

declare(strict_types=1);

namespace Rvvup\Payments\Observer\Model\ProcessOrder;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterfaceFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\Payment as PaymentResource;
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

    /** @var PaymentResource */
    private $paymentResource;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory
     * @param OrderManagementInterface $orderManagement
     * @param PaymentResource $paymentResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderRepositoryInterface                         $orderRepository,
        OrderStatusHistoryInterfaceFactory               $orderStatusHistoryFactory,
        OrderManagementInterface                         $orderManagement,
        PaymentResource                                  $paymentResource,
        LoggerInterface                                  $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderStatusHistoryFactory = $orderStatusHistoryFactory;
        $this->orderManagement = $orderManagement;
        $this->paymentResource = $paymentResource;
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
        $paymentData = $rvvupData['payments'][0];
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

            $payment = $order->getPayment();
            foreach ($keys as $key) {
                $this->populateCardData($data, $paymentData, $key, $payment);
            }
            $this->paymentResource->save($payment);

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
     * @param array $paymentData
     * @param string $key
     * @param OrderPaymentInterface $payment
     * @return void
     */
    private function populateCardData(
        array &$data,
        array $paymentData,
        string $key,
        OrderPaymentInterface $payment
    ): void {
        if (isset($paymentData[$key])) {
            $value = $this->mapCardValue($paymentData[$key]);
            $payment->setAdditionalInformation('rvvup_' . $key, $paymentData[$key]);
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
