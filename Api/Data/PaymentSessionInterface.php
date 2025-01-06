<?php

declare(strict_types=1);

namespace Rvvup\Payments\Api\Data;

interface PaymentSessionInterface
{
    /**
     * @return string
     */
    public function getPaymentSessionId(): string;

    /**
     * @param string $paymentSessionId
     * @return void
     */
    public function setPaymentSessionId(string $paymentSessionId): void;

    /**
     * @return string
     */
    public function getRedirectUrl(): string;

    /**
     * @param string $redirectUrl
     * @return void
     */
    public function setRedirectUrl(string $redirectUrl): void;
}
