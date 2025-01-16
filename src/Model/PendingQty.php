<?php

namespace Rvvup\Payments\Model;

use Magento\Framework\DataObject;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderItemInterface;

class PendingQty
{
    public const SALES_ORDER = 'sales_order';

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @param Json $serializer
     */
    public function __construct(
        Json $serializer
    ) {
        $this->serializer = $serializer;
    }

    /**
     * @param DataObject $subject
     * @return bool
     */
    public function isRefundApplicable(DataObject $subject): bool
    {
        if ($subject->getEventPrefix() == self::SALES_ORDER) {
            $order = $subject;
        } else {
            $order = $subject->getOrder();
        }

        foreach ($order->getAllItems() as $item) {
            if ($item->getQtyInvoiced() - $item->getQtyRefunded() - $this->getRvvupPendingQty($item) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param OrderItemInterface $item
     * @return int
     */
    public function getRvvupPendingQty(OrderItemInterface $item): int
    {
        if (empty($item->getRvvupPendingRefundData())) {
            return 0;
        }

        $qty = 0;
        $data = $this->serializer->unserialize($item->getRvvupPendingRefundData());

        foreach ($data as $item) {
            $qty += (int)$item['qty'];
        }

        return $qty;
    }
}
