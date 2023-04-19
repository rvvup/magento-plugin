<?php

declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Command;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Rvvup\Payments\Model\SdkProxy;
use Rvvup\Sdk\Factories\Inputs\RefundCreateInputFactory;

class Refund implements CommandInterface
{
    /** @var SdkProxy */
    private $sdkProxy;

    /**
     * @var RefundCreateInputFactory
     */
    private RefundCreateInputFactory $refundCreateInputFactory;

    /**
     * @var Json
     */
    private Json $serializer;

    /**
     * @var OrderItemRepositoryInterface
     */
    private OrderItemRepositoryInterface $orderItemRepository;

    /**
     * @var CreditmemoRepositoryInterface
     */
    private CreditmemoRepositoryInterface $creditmemoRepository;

    /**
     * @param SdkProxy $sdkProxy
     * @param RefundCreateInputFactory $refundCreateInputFactory
     * @param Json $serializer
     * @param OrderItemRepositoryInterface $orderItemRepository
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     */
    public function __construct(
        SdkProxy $sdkProxy,
        RefundCreateInputFactory $refundCreateInputFactory,
        Json $serializer,
        OrderItemRepositoryInterface $orderItemRepository,
        CreditmemoRepositoryInterface $creditmemoRepository
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->refundCreateInputFactory = $refundCreateInputFactory;
        $this->serializer = $serializer;
        $this->orderItemRepository = $orderItemRepository;
        $this->creditmemoRepository = $creditmemoRepository;
    }

    public function execute(array $commandSubject)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $commandSubject['payment']->getPayment();
        $idempotencyKey = $payment->getTransactionId() . '-' . time();

        $order = $payment->getOrder();

        $input = $this->refundCreateInputFactory->create(
            $payment->getAdditionalInformation('rvvup_order_id'),
            $commandSubject['amount'],
            $order->getOrderCurrency()->getCurrencyCode(),
            $idempotencyKey,
            $payment->getCreditMemo() !== null ? $payment->getCreditMemo()->getCustomerNote() : ""
        );

        $result = $this->sdkProxy->refundCreate($input);
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
     * @param $orderItem
     * @param $item
     * @param $payment
     * @return void
     */
    private function setPendingRefundData($orderItem, $item, $payment, $refundId): void
    {
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
