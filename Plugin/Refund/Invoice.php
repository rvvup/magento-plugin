<?php

declare(strict_types=1);

namespace Rvvup\Payments\Plugin\Refund;

use Magento\Sales\Model\Order\Invoice as BaseInvoice;
use Rvvup\Payments\Model\PendingQty;

class Invoice
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
     * @param BaseInvoice $subject
     * @param bool $result
     * @return bool
     */
    public function afterCanRefund(BaseInvoice $subject, bool $result): bool
    {
        return $this->pendingQtyService->isRefundApplicable($subject, $result);
    }

    /**
     * @param BaseInvoice $subject
     * @param $result
     * @return bool
     */
    public function afterGetIsUsedForRefund(BaseInvoice $subject, $result): bool
    {
        return !$this->pendingQtyService->isRefundApplicable($subject, $result);
    }
}
