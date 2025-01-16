<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\Webhook;

class WebhookEventType
{
    public const PAYMENT_AUTHORIZED = 'PAYMENT_AUTHORIZED';
    public const PAYMENT_COMPLETED = 'PAYMENT_COMPLETED';

    public const REFUND_COMPLETED = 'REFUND_COMPLETED';
}
