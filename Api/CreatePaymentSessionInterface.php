<?php

declare(strict_types=1);

namespace Rvvup\Payments\Api;

interface CreatePaymentSessionInterface
{
    /**
     * Get the payment actions for the masked cart ID.
     *
     * @param string $cartId
     * @param string $checkoutId
     * @return string
     */
    public function execute(string $cartId, string $checkoutId): string;
}
