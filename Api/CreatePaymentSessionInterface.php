<?php

declare(strict_types=1);

namespace Rvvup\Payments\Api;

use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Rvvup\Payments\Api\Data\CreatePaymentSessionResponseInterface;

interface CreatePaymentSessionInterface
{
    /**
     * Create payment session with checkout for a cart
     *
     * @param string $cartId
     * @param string $checkoutId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface $billingAddress
     * @return CreatePaymentSessionResponseInterface
     */
    public function execute(
        string                                   $cartId,
        string                                   $checkoutId,
        string                                   $email,
        PaymentInterface                         $paymentMethod,
        AddressInterface $billingAddress
    ): CreatePaymentSessionResponseInterface;
}
