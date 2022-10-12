<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

interface PaymentActionsGetInterface
{
    /**
     * Get the payment actions for the cart ID & customer ID if provided.
     *
     * @param string $cartId
     * @param string|null $customerId
     * @return \Rvvup\Payments\Api\Data\PaymentActionInterface[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(string $cartId, ?string $customerId = null): array;
}
