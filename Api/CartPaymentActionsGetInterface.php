<?php

declare(strict_types=1);

namespace Rvvup\Payments\Api;

interface CartPaymentActionsGetInterface
{
    /**
     * Get the payment actions for the cart ID.
     *
     * @param string $cartId
     * @param bool $expressActions
     * @return \Rvvup\Payments\Api\Data\PaymentActionInterface[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(string $cartId, bool $expressActions = false): array;
}
