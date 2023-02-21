<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Checks;

interface HasCartExpressPaymentInterface
{
    /**
     * Check whether a cart is for an express payment.
     *
     * @param int $cartId
     * @return bool
     */
    public function execute(int $cartId): bool;
}
