<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Checks;

use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\Logger;
use Rvvup\Payments\Gateway\Method;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\PaymentMethodManagementInterface;

class HasCartExpressPayment implements HasCartExpressPaymentInterface
{
    /**
     * @var \Magento\Quote\Api\PaymentMethodManagementInterface
     */
    private $paymentMethodManagement;

    /**
     * Set via etc/di.xml
     *
     * @var \Psr\Log\LoggerInterface|Logger
     */
    private $logger;

    /**
     * @param \Magento\Quote\Api\PaymentMethodManagementInterface $paymentMethodManagement
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(PaymentMethodManagementInterface $paymentMethodManagement, LoggerInterface $logger)
    {
        $this->paymentMethodManagement = $paymentMethodManagement;
        $this->logger = $logger;
    }

    /**
     * Check whether a cart is for an express payment.
     *
     * @param int $cartId
     * @return bool
     */
    public function execute(int $cartId): bool
    {
        try {
            $payment = $this->paymentMethodManagement->get($cartId);

            // No action if no payment or payment without a method set.
            if ($payment === null || !$payment->getId() || $payment->getMethod() === null) {
                return false;
            }
        } catch (NoSuchEntityException $ex) {
            // On no such entity exception, just return false.
            $this->logger->error(
                'Failed to check whether a cart has an express payment with error: ' . $ex->getMessage()
            );

            return false;
        }

        // No action if not Rvvup Payment.
        if (strpos($payment->getMethod(), Method::PAYMENT_TITLE_PREFIX) !== 0) {
            return false;
        }

        // If we don't have a payment or express payment key is not set, return as is.
        if ($payment->getadditionalInformation(Method::EXPRESS_PAYMENT_KEY) !== true) {
            return false;
        }

        return true;
    }
}
