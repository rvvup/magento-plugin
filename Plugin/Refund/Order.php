<?php

declare(strict_types=1);

namespace Rvvup\Payments\Plugin\Refund;

use Rvvup\Payments\Model\PendingQty;

class Order
{
    /**
     * @var PendingQty
     */
    private PendingQty $pendingQtyService;

    /**
     * @param PendingQty $pendingQtyService
     */
    public function __construct(
        PendingQty $pendingQtyService
    ) {
        $this->pendingQtyService = $pendingQtyService;
    }

    /**
     * @param Order $subject
     * @param bool $result
     * @return bool
     */
    public function afterCanCreditmemo(\Magento\Sales\Model\Order $subject, bool $result): bool
    {
        return $this->pendingQtyService->isRefundApplicable($subject, $result);
    }
}
