<?php

declare(strict_types=1);

namespace Rvvup\Payments\Plugin\Refund;

use Magento\Sales\Model\Order\Creditmemo as BaseCreditmemo;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\PendingQty;

class CreditMemo
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
     * @param BaseCreditmemo $subject
     * @param bool $result
     * @return bool
     */
    public function afterCanRefund(BaseCreditmemo $subject, bool $result): bool
    {
        $method = $subject->getOrder()->getPayment()->getMethod();
        if (strpos($method, Method::PAYMENT_TITLE_PREFIX) === 0) {
            if (!$result) {
                return false;
            }
            return $this->pendingQtyService->isRefundApplicable($subject);
        }

        return $result;
    }
}
