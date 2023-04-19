<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\ProcessRefund;

use Exception;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Exception\PaymentValidationException;

class Complete implements ProcessorInterface
{
    public const TYPE = 'REFUND_COMPLETED';

    /**
     * @var LoggerInterface|RvvupLog
     */
    private $logger;

    /**
     * @var Json
     */
    private Json $serializer;

    /**
     * @var OrderItemRepositoryInterface
     */
    private OrderItemRepositoryInterface $orderItemRepository;


    /**
     * @param LoggerInterface $logger
     * @param Json $serializer
     * @param OrderItemRepositoryInterface $orderItemRepository
     */
    public function __construct(
        LoggerInterface $logger,
        Json $serializer,
        OrderItemRepositoryInterface $orderItemRepository
    ) {
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->orderItemRepository = $orderItemRepository;
    }

    /**
     * Complete refund
     *
     * @param OrderInterface $order
     * @param array $payload
     * @return void
     * @throws PaymentValidationException
     */
    public function execute(OrderInterface $order, array $payload): void
    {
        if (!$order->getId()) {
            $this->writeErrorMessage($payload);
            return;
        }

        try {
            foreach ($order->getItems() as $item) {
                if (!$item->getRvvupPendingRefundData()) {
                    continue;
                } else {
                    $data = $this->serializer->unserialize($item->getRvvupPendingRefundData());
                    $this->completeRefund($data, $payload, $item);
                }
            }
        } catch (Exception $e) {
            $this->writeErrorMessage($payload);
        }
    }

    /**
     * @param $data
     * @param $payload
     * @param $item
     * @return void
     */
    private function completeRefund($data, $payload, $item): void
    {
        foreach ($data as $creditMemoId => $refundData) {
            if ($refundData['refund_id'] == $payload['refund_id']) {
                $item->setQtyRefunded($item->getQtyRefunded() + (int)$refundData['qty']);
                $refundData['qty'] = 0;
                $data[$creditMemoId] = $refundData;
                $item->setRvvupPendingRefundData($this->serializer->serialize($data));
                $this->orderItemRepository->save($item);
            }
        }
    }

    /**
     * @param $payload
     * @return void
     */
    private function writeErrorMessage($payload) {
        $data = $this->serializer->serialize($payload);
        $this->logger->error(
            'Error during refund processing with data' . json_encode($data)
        );
    }
}
