<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\Payment;

class Rvvup
{
    /**
     * Curative list of available RVVUP Status constants.
     *
     * CANCELLED: Customer aborted the payment.
     * DECLINED: Customer's issuing bank rejected the payment.
     * EXPIRED: Customer's pending payment time to complete has expired.
     * PENDING: Customer has clicked place order but has not finished all steps.
     * REQUIRES_ACTION: This needs to do something to finalise the transaction
     * SUCCEEDED: Payment was completed successfully.
     */
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_DECLINED = 'DECLINED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_REQUIRES_ACTION = 'REQUIRES_ACTION';
    public const STATUS_SUCCEEDED = 'SUCCEEDED';
}
