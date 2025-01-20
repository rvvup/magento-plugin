<?php

declare(strict_types=1);

namespace Rvvup\Payments\Plugin\Quote\Api\PaymentMethodManagement;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\CartExpressPaymentRemove;

class RemoveExpressPaymentInfoOnMethodChange
{
    /**
     * @var \Rvvup\Payments\Model\CartExpressPaymentRemove
     */
    private $cartExpressPaymentRemove;

    /**
     * Set via etc/webapi_rest/di.xml
     *
     * @var \Psr\Log\LoggerInterface|RvvupLog
     */
    private $logger;

    /**
     * @param \Rvvup\Payments\Model\CartExpressPaymentRemove $cartExpressPaymentRemove
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(CartExpressPaymentRemove $cartExpressPaymentRemove, LoggerInterface $logger)
    {
        $this->cartExpressPaymentRemove = $cartExpressPaymentRemove;
        $this->logger = $logger;
    }

    /**
     * Remove express payment method additional information if payment method has changed.
     *
     * @param \Magento\Quote\Api\PaymentMethodManagementInterface $subject
     * @param int $cartId
     * @param \Magento\Quote\Api\Data\PaymentInterface $method
     * @return null
     */
    public function beforeSet(PaymentMethodManagementInterface $subject, $cartId, PaymentInterface $method)
    {
        // First get existing payment method for cart, return as is if none found.
        try {
            $payment = $subject->get($cartId);
        } catch (NoSuchEntityException $ex) {
            return null;
        }

        if ($payment === null || !$payment->getId()) {
            return null;
        }

        // Then check if we handle the same payment method, if yes, we safely assume it's for the express payment.
        if ($payment->getMethod() === $method->getMethod()) {
            return null;
        }

        // Finally, perform removing the express payment data.
        if (!$this->cartExpressPaymentRemove->execute((string) $cartId)) {
            $this->logger->error('Failed to remove express payment data on payment method change');
        }

        return null;
    }
}
