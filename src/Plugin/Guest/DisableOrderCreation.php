<?php

namespace Rvvup\Payments\Plugin\Guest;

use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Rvvup\Payments\Gateway\Method;

class DisableOrderCreation
{
    /** @var CartRepositoryInterface */
    private $cartRepository;

    /**
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(CartRepositoryInterface $cartRepository)
    {
        $this->cartRepository = $cartRepository;
    }

    /**
     * @param GuestPaymentInformationManagementInterface $subject
     * @param callable $proceed
     * @param string $cartId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return int
     * @throws NoSuchEntityException
     */
    public function aroundSavePaymentInformationAndPlaceOrder(
        GuestPaymentInformationManagementInterface $subject,
        callable                                   $proceed,
        $cartId,
        $email,
        PaymentInterface                           $paymentMethod,
        ?AddressInterface                           $billingAddress = null
    ): int {
        // Return reserved order id if Rvvup Payment.
        if (strpos($paymentMethod->getMethod(), Method::PAYMENT_TITLE_PREFIX) === 0) {
            $cart = $this->cartRepository->get($cartId);
            $id = $cart->getReservedOrderId();
            if (!$id) {
                $cart->reserveOrderId();
                $id = $cart->getReservedOrderId();
                $this->cartRepository->save($cart);
            }
            return $id;
        }
        return $proceed($cartId, $email, $paymentMethod, $billingAddress);
    }
}
