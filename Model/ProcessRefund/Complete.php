<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\ProcessRefund;

use Exception;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\Logger;
use Rvvup\Payments\Service\Capture;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Rvvup\Payments\Exception\PaymentValidationException;

class Complete implements ProcessorInterface
{
    public const TYPE = 'REFUND_COMPLETED';

    /**
     * @var LoggerInterface|Logger
     */
    private $logger;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var OrderItemRepositoryInterface
     */
    private $orderItemRepository;

    /**
     * @var Capture
     */
    private $captureService;

    /**
     * @param LoggerInterface $logger
     * @param Json $serializer
     * @param OrderItemRepositoryInterface $orderItemRepository
     * @param Capture $captureService
     */
    public function __construct(
        LoggerInterface $logger,
        Json $serializer,
        OrderItemRepositoryInterface $orderItemRepository,
        Capture $captureService
    ) {
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->orderItemRepository = $orderItemRepository;
        $this->captureService = $captureService;
    }

    /**
     * Complete refund
     * @param array $payload
     * @return void
     * @throws Exception
     */
    public function execute(array $payload): void
    {
        $order = $this->captureService->getOrderByRvvupId($payload['order_id']);

        if (!$order || !$order->getId()) {
            $this->writeErrorMessage($payload);
            return;
        }

        try {
            $this->refundItems($order->getItems(), $payload);
        } catch (Exception $e) {
            $this->writeErrorMessage($payload);
        }
    }

    /**
     * @param array $items
     * @param array $payload
     * @return void
     */
    private function refundItems(array $items, array $payload): void
    {
        foreach ($items as $item) {
            if (!$item->getRvvupPendingRefundData()) {
                continue;
            } else {
                $data = $this->serializer->unserialize($item->getRvvupPendingRefundData());
                $this->completeRefund($data, $payload, $item);
            }
        }
    }

    /**
     * @param array $data
     * @param array $payload
     * @param OrderItemInterface $item
     * @return void
     */
    private function completeRefund(array $data, array $payload, OrderItemInterface $item): void
    {
        foreach ($data as $creditMemoId => $refundData) {
            if ($refundData['refund_id'] == $payload['refund_id']) {
                if ($refundData['qty'] > 0) {
                    $item->setQtyRefunded($item->getQtyRefunded() + (int)$refundData['qty']);
                    $refundData['qty'] = 0;
                    $data[$creditMemoId] = $refundData;
                    $item->setRvvupPendingRefundData($this->serializer->serialize($data));
                    $this->orderItemRepository->save($item);
                }
            }
        }
    }

    /**
     * @param array $payload
     * @return void
     */
    private function writeErrorMessage(array $payload): void
    {
        $data = $this->serializer->serialize($payload);
        $this->logger->error(
            'Error during refund processing with data' . json_encode($data)
        );
    }
}
