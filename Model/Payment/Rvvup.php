<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\Payment;

class Rvvup
{
    /** @var string The needs to do something to finalise the transaction */
    public const STATUS_REQUIRES_ACTION = 'REQUIRES_ACTION';
    /** @var string Customer has clicked place order but has not finished all steps  */
    public const STATUS_PENDING = 'PENDING';
    /** @var string Payment was completed sucessfully */
    public const STATUS_SUCCEEDED = 'SUCCEEDED';
    /** @var string Customer aborted the payment */
    public const STATUS_CANCELLED = 'CANCELLED';
    /** @var string Customer's issuing bank rejected the payment */
    public const STATUS_DECLINED = 'DECLINED';
}
