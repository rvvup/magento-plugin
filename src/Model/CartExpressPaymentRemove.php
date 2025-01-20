<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Quote\Model\ResourceModel\Quote\Payment;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\CartExpressPaymentRemoveInterface;
use Rvvup\Payments\Gateway\Method;

class CartExpressPaymentRemove implements CartExpressPaymentRemoveInterface
{
    /**
     * @var \Magento\Quote\Api\PaymentMethodManagementInterface
     */
    private $paymentMethodManagement;

    /**
     * @var \Magento\Quote\Model\ResourceModel\Quote\Payment
     */
    private $paymentResource;

    /**
     * Set via etc/di.xml
     *
     * @var \Psr\Log\LoggerInterface|RvvupLog
     */
    private $logger;

    /**
     * @param \Magento\Quote\Api\PaymentMethodManagementInterface $paymentMethodManagement
     * @param \Magento\Quote\Model\ResourceModel\Quote\Payment $paymentResource
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    public function __construct(
        PaymentMethodManagementInterface $paymentMethodManagement,
        Payment $paymentResource,
        LoggerInterface $logger
    ) {
        $this->paymentMethodManagement = $paymentMethodManagement;
        $this->paymentResource = $paymentResource;
        $this->logger = $logger;
    }

    /**
     * Remove the payment data of express payment for the specified cart & rvvup payment method for a guest user.
     *
     * Return true on success or "dummy" true when the methods are not matching.
     *
     * @param string $cartId
     * @return bool
     */
    public function execute(string $cartId): bool
    {
        try {
            $payment = $this->paymentMethodManagement->get($cartId);

            // No action if no payment or payment without a method set.
            if ($payment === null || !$payment->getId() || $payment->getMethod() === null) {
                return true;
            }

            // No action if not Rvvup Payment.
            if (strpos($payment->getMethod(), Method::PAYMENT_TITLE_PREFIX) !== 0) {
                return true;
            }

            // No action if no express payment on quote.
            if ($payment->getAdditionalInformation(Method::EXPRESS_PAYMENT_KEY) !== true) {
                return true;
            }
        } catch (NoSuchEntityException $ex) {
            // Return if no cart found.
            return true;
        }

        try {
            // Unset express data.
            $payment->unsAdditionalInformation(Method::ORDER_ID);
            $payment->unsAdditionalInformation(Method::EXPRESS_PAYMENT_KEY);
            $payment->unsAdditionalInformation(Method::EXPRESS_PAYMENT_DATA_KEY);

            $this->paymentResource->save($payment);

            return true;
        } catch (Exception $ex) {
            // Log any other error (on save) and return
            $this->logger->error(
                'Error thrown on removing express payment information with message: ' . $ex->getMessage(),
                [
                    'quote_id' => $cartId,
                    'payment_id' => $payment->getId()
                ]
            );

            return false;
        }
    }
}
