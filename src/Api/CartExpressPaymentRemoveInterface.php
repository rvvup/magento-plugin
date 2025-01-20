<?php

declare(strict_types=1);

namespace Rvvup\Payments\Api;

interface CartExpressPaymentRemoveInterface
{
    /**
     * Remove the payment data of express payment for the specified cart.
     *
     * @param string $cartId
     * @return bool
     */
    public function execute(string $cartId): bool;
}
