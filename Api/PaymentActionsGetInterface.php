<?php

declare(strict_types=1);

namespace Rvvup\Payments\Api;

interface PaymentActionsGetInterface
{
    /**
     * Get the payment actions for the customer ID & cart ID.
     *
     * @param string $customerId
     * @param string $cartId
     * @return \Rvvup\Payments\Api\Data\PaymentActionInterface[]
     */
    public function execute(string $customerId, string $cartId): array;
}
