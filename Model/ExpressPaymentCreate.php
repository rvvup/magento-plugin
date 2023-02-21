<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Rvvup\Payments\Api\CartPaymentActionsGetInterface;
use Rvvup\Payments\Api\ExpressPaymentCreateInterface;
use Rvvup\Payments\Exception\PaymentValidationException;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Payment\PaymentCreateExpressInterface;

class ExpressPaymentCreate implements ExpressPaymentCreateInterface
{
    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var \Magento\Quote\Api\PaymentMethodManagementInterface
     */
    private $paymentMethodManagement;

    /**
     * @var \Rvvup\Payments\Api\CartPaymentActionsGetInterface
     */
    private $cartPaymentActionsGet;

    /**
     * @var \Rvvup\Payments\Model\Payment\PaymentCreateExpressInterface
     */
    private $paymentExpressCreate;

    /**
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
     * @param \Magento\Quote\Api\PaymentMethodManagementInterface $paymentMethodManagement
     * @param \Rvvup\Payments\Api\CartPaymentActionsGetInterface $cartPaymentActionsGet
     * @param \Rvvup\Payments\Model\Payment\PaymentCreateExpressInterface $paymentExpressCreate
     * @return void
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        PaymentMethodManagementInterface $paymentMethodManagement,
        CartPaymentActionsGetInterface $cartPaymentActionsGet,
        PaymentCreateExpressInterface $paymentExpressCreate
    ) {
        $this->cartRepository = $cartRepository;
        $this->paymentMethodManagement = $paymentMethodManagement;
        $this->cartPaymentActionsGet = $cartPaymentActionsGet;
        $this->paymentExpressCreate = $paymentExpressCreate;
    }

    /**
     * Create a Rvvup Express order for the specified cart & rvvup payment method.
     *
     * @param string $cartId
     * @param string $methodCode
     * @return \Rvvup\Payments\Api\Data\PaymentActionInterface[]
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Rvvup\Payments\Exception\PaymentValidationException
     */
    public function execute(string $cartId, string $methodCode): array
    {
        $quote = $this->cartRepository->getActive($cartId);

        $result = $this->paymentExpressCreate->execute($quote, $methodCode);

        if (!$result) {
            throw new PaymentValidationException(__('Payment method not available'));
        }

        $payment = $this->paymentMethodManagement->get($cartId);

        if ($payment === null || $payment->getAdditionalInformation(Method::EXPRESS_PAYMENT_KEY) !== true) {
            throw new PaymentValidationException(__('Invalid payment method'));
        }

        $rvvupOrderId = $payment->getAdditionalInformation(Method::ORDER_ID);

        if (!is_string($rvvupOrderId)) {
            throw new PaymentValidationException(__('Invalid payment method'));
        }

        return $this->cartPaymentActionsGet->execute($cartId, true);
    }
}
