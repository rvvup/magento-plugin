<?php

declare(strict_types=1);

namespace Rvvup\Payments\Api;

interface GuestExpressPaymentCreateInterface
{
    /**
     * Create an Express order for the specified cart & rvvup payment method for a guest user.
     *
     * @param string $cartId
     * @param string $methodCode
     * @return \Rvvup\Payments\Api\Data\PaymentActionInterface[]
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Rvvup\Payments\Exception\PaymentValidationException
     */
    public function execute(string $cartId, string $methodCode): array;
}
