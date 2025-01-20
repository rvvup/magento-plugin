<?php

declare(strict_types=1);

namespace Rvvup\Payments\Service\Card;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderStatusHistoryInterfaceFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Gateway\Method;

class CardMetaService
{

    /** @var LoggerInterface $logger */
    private $logger;

    /** @var OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory */
    private $orderStatusHistoryFactory;

    /** @var OrderManagementInterface $orderManagement */
    private $orderManagement;

    /**
     * @param OrderStatusHistoryInterfaceFactory $orderStatusHistoryFactory
     * @param OrderManagementInterface $orderManagement
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderStatusHistoryInterfaceFactory               $orderStatusHistoryFactory,
        OrderManagementInterface                         $orderManagement,
        LoggerInterface                                  $logger
    ) {
        $this->orderStatusHistoryFactory = $orderStatusHistoryFactory;
        $this->orderManagement = $orderManagement;
        $this->logger = $logger;
    }

    /**
     * @param array $rvvupPaymentResponse
     * @param OrderInterface $order
     * @throws AlreadyExistsException
     */
    public function process(array $rvvupPaymentResponse, OrderInterface $order)
    {
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
        $message = "<strong>Rvvup Card Data:</strong> <br />";
        $addHistoryComment = false;
        foreach ($keys as $key) {
            if (isset($rvvupPaymentResponse[$key])) {
                $payment->setAdditionalInformation(Method::PAYMENT_TITLE_PREFIX . $key, $rvvupPaymentResponse[$key]);
                $message .= $key . ': ' . $this->mapCardValue($rvvupPaymentResponse[$key]) . ' <br />';
                $addHistoryComment = true;
            }
        }
        if ($addHistoryComment) {
            try {
                $historyComment = $this->orderStatusHistoryFactory->create();
                $historyComment->setParentId($order->getEntityId());
                $historyComment->setIsCustomerNotified(0);
                $historyComment->setIsVisibleOnFront(0);
                $historyComment->setStatus($order->getStatus());
                $historyComment->setComment(__($message));
                $this->orderManagement->addComment($order->getEntityId(), $historyComment);
            } catch (\Exception $e) {
                $this->logger->error('Rvvup cards metadata comment failed with exception: ' . $e->getMessage());
            }
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
