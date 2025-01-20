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
    private $pendingQtyService;

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
        return $this->pendingQtyService->isRefundApplicable($subject);
    }

    /**
     * @param BaseInvoice $subject
     * @param int|null $result
     * @return bool
     */
    public function afterGetIsUsedForRefund(BaseInvoice $subject, ?int $result): bool
    {
        return !$this->pendingQtyService->isRefundApplicable($subject);
    }
}
