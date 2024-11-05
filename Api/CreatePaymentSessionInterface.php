<?php

declare(strict_types=1);

namespace Rvvup\Payments\Api;

use Rvvup\Payments\Api\Data\CreatePaymentSessionResponseInterface;

interface CreatePaymentSessionInterface
{
    /**
     * Create payment session with checkout for a cart
     *
     * @param string $cartId
     * @param string $checkoutId
     * @return CreatePaymentSessionResponseInterface
     */
    public function execute(string $cartId, string $checkoutId): CreatePaymentSessionResponseInterface;
}
