<?php

declare(strict_types=1);

namespace Rvvup\Payments\Api;

interface GuestCartExpressPaymentRemoveInterface
{
    /**
     * Remove the payment data of express payment for the specified cart & rvvup payment method for a guest user.
     *
     * @param string $cartId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(string $cartId): bool;
}
