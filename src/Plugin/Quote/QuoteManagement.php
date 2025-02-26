<?php

namespace Rvvup\Payments\Plugin\Quote;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote as QuoteEntity;
use Rvvup\Payments\Gateway\Method;

class QuoteManagement
{
    /**
     * @param \Magento\Quote\Model\QuoteManagement $subject
     * @param QuoteEntity $quote
     * @param array $orderData
     * @return array
     * @throws LocalizedException
     */
    public function beforeSubmit(
        \Magento\Quote\Model\QuoteManagement $subject,
        QuoteEntity                          $quote,
        array $orderData = []
    ): array {
        $payment = $quote->getPayment();
        if (strpos($payment->getMethod(), Method::PAYMENT_TITLE_PREFIX) !== 0) {
            return [$quote, $orderData];
        }
        // We want to check if rvvup payment has been created and attached to the quote before we allow order creation,
        // but only if it's not an admin order method.
        if (!$payment->getMethodInstance()->canUseInternal()) {
            if ($payment->getAdditionalInformation(Method::ORDER_ID) === null ||
                $payment->getAdditionalInformation(Method::PAYMENT_ID) === null) {
                throw new LocalizedException(
                    __('Cannot complete payment as it has not been authorized. Please try again')
                );
            }
        }

        return [$quote, $orderData];
    }
}
