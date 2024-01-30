<?php

namespace Rvvup\Payments\Plugin\Customer;

use Magento\Checkout\Api\PaymentInformationManagementInterface;
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
    public function __construct(
        CartRepositoryInterface $cartRepository
    ) {
        $this->cartRepository = $cartRepository;
    }

    /**
     * @param PaymentInformationManagementInterface $subject
     * @param callable $proceed
     * @param int $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface|null $billingAddress
     * @return int
     * @throws NoSuchEntityException
     */
    public function aroundSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagementInterface    $subject,
        callable                                 $proceed,
        $cartId,
        PaymentInterface                         $paymentMethod,
        AddressInterface                         $billingAddress = null
    ): int {
        // Return reserved order id if Rvvup Payment.
        if (strpos($paymentMethod->getMethod(), Method::PAYMENT_TITLE_PREFIX) === 0) {
            $cart = $this->cartRepository->get($cartId);
            $id = $cart->getReservedOrderId();
            if (!$id) {
                $cart->reserveOrderId();
                $this->cartRepository->save($cart);
                $id = $cart->getReservedOrderId();
            }
            return $id;
        }
        return $proceed($cartId, $paymentMethod, $billingAddress);
    }
}
