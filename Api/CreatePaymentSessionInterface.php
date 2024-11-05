<?php

declare(strict_types=1);

namespace Rvvup\Payments\Api;

interface CreatePaymentSessionInterface
{
    /**
     * Create payment session with checkout for a cart
     *
     * @param string $cartId
     * @param string $checkoutId
     * @return string
     */
    public function execute(string $cartId, string $checkoutId): string;
}
