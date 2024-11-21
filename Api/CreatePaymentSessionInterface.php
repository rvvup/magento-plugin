<?php

declare(strict_types=1);

namespace Rvvup\Payments\Api;

use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Rvvup\Payments\Api\Data\CreatePaymentSessionResponseInterface;

interface CreatePaymentSessionInterface
{
    /**
     * Create payment session with checkout for a guest cart
     *
     * @param string $cartId
     * @param string $checkoutId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface $billingAddress
     * @return CreatePaymentSessionResponseInterface
     */
    public function guestRoute(
        string                                   $cartId,
        string                                   $checkoutId,
        string                                   $email,
        PaymentInterface                         $paymentMethod,
        AddressInterface $billingAddress
    ): CreatePaymentSessionResponseInterface;

    /**
     * Create payment session with checkout for a customer cart
     *
     * @param string $customerId
     * @param string $cartId
     * @param string $checkoutId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface $billingAddress
     * @return CreatePaymentSessionResponseInterface
     */
    public function customerRoute(
        string           $customerId,
        string           $cartId,
        string           $checkoutId,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress
    ): CreatePaymentSessionResponseInterface;
}
