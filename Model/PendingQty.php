<?php

namespace Rvvup\Payments\Model;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order\Creditmemo\Item;

class PendingQty
{
    private const SALES_ORDER = 'sales_order';

    /**
     * @var Json
     */
    private Json $serializer;

    /**
     * @param Json $serializer
     */
    public function __construct(
        Json $serializer
    ) {
        $this->serializer = $serializer;
    }

    /**
     * @param $subject
     * @param bool $result
     * @return bool
     */
    public function isRefundApplicable($subject, bool $result): bool
    {
        if ($subject->getEventPrefix() == self::SALES_ORDER) {
            $order = $subject;
        } else {
            $order = $subject->getOrder();
        }

        foreach ($order->getAllItems() as $item) {
            if ($item->getQtyInvoiced() - $item->getQtyRefunded() - $this->getRvvupPendingQty($item) <= 0) {
                return false;
            }
        }

        return $result;
    }

    /**
     * @param OrderItemInterface $item
     * @return float|int
     */
    public function getRvvupPendingQty(OrderItemInterface $item)
    {
        if (empty($item->getRvvupPendingRefundData())) {
            return 0;
        }

        $qty = 0;
        $data = $this->serializer->unserialize($item->getRvvupPendingRefundData());

        foreach ($data as $item) {
            $qty += $item['qty'];
        }

        return $qty;
    }
}
