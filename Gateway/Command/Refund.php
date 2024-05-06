<?php

declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoItemInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Payment;
use Rvvup\Payments\Model\SdkProxy;
use Rvvup\Payments\Service\Cache;
use Rvvup\Sdk\Factories\Inputs\RefundCreateInputFactory;

class Refund implements CommandInterface
{
    /** @var SdkProxy */
    private $sdkProxy;

    /**
     * @var RefundCreateInputFactory
     */
    private $refundCreateInputFactory;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var OrderItemRepositoryInterface
     */
    private $orderItemRepository;

    /**
     * @var CreditmemoRepositoryInterface
     */
    private $creditmemoRepository;

    /** @var Cache  */
    private $cache;

    /**
     * @param SdkProxy $sdkProxy
     * @param RefundCreateInputFactory $refundCreateInputFactory
     * @param Json $serializer
     * @param OrderItemRepositoryInterface $orderItemRepository
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param Cache $cache
     */
    public function __construct(
        SdkProxy $sdkProxy,
        RefundCreateInputFactory $refundCreateInputFactory,
        Json $serializer,
        OrderItemRepositoryInterface $orderItemRepository,
        CreditmemoRepositoryInterface $creditmemoRepository,
        Cache $cache
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->refundCreateInputFactory = $refundCreateInputFactory;
        $this->serializer = $serializer;
        $this->orderItemRepository = $orderItemRepository;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->cache = $cache;
    }

    public function execute(array $commandSubject)
    {
        /** @var Payment $payment */
        $payment = $commandSubject['payment']->getPayment();
        $idempotencyKey = $payment->getTransactionId() ?: $payment->getAdditionalInformation('rvvup_payment_id') . '-' . time();

        $order = $payment->getOrder();
        $rvvupOrderId = $payment->getAdditionalInformation('rvvup_order_id');
        $orderState = $payment->getOrder()->getState();

        $input = $this->refundCreateInputFactory->create(
            $rvvupOrderId,
            $commandSubject['amount'],
            $order->getOrderCurrency()->getCurrencyCode(),
            $idempotencyKey,
            $payment->getCreditMemo() !== null ? $payment->getCreditMemo()->getCustomerNote() : ""
        );

        $result = $this->sdkProxy->refundCreate($input);
        $this->cache->clear($rvvupOrderId, $orderState);
        $refundId = $result['id'];
        $refundStatus = $result['status'];

        switch ($refundStatus) {
            case 'SUCCEEDED':
                $payment->setTransactionId($refundId);
                break;

            case 'PENDING':
                $payment->setTransactionId($refundId);
                $payment->setIsTransactionPending(true);
                foreach ($payment->getCreditmemo()->getItems() as $item) {
                    $orderItem = $item->getOrderItem();
                    $this->setPendingRefundData($orderItem, $item, $payment, $refundId);
                }
                break;

            case 'FAILED':
                throw new CommandException(__("There was an error whilst refunding"));
        }
        return $this;
    }

    /**
     * @param Item $orderItem
     * @param CreditmemoItemInterface $item
     * @param Payment $payment
     * @param string $refundId
     * @return void
     */
    private function setPendingRefundData(
        Item $orderItem,
        CreditmemoItemInterface $item,
        Payment $payment,
        string $refundId
    ): void {
        $creditMemo = $this->creditmemoRepository->save($payment->getCreditmemo());
        if ($orderItem->getRvvupPendingRefundData()) {
            $data = $this->serializer->unserialize($orderItem->getRvvupPendingRefundData());
        } else {
            $data = [];
        }
        $data[$creditMemo->getId()] = ['qty' => $item->getQty(), 'refund_id' => $refundId];
        $orderItem->setRvvupPendingRefundData($this->serializer->serialize($data));
        $orderItem->setQtyRefunded($orderItem->getQtyRefunded() - $item->getQty());
        $this->orderItemRepository->save($orderItem);
    }
}
