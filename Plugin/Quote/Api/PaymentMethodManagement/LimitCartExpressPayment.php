<?php

declare(strict_types=1);

namespace Rvvup\Payments\Plugin\Quote\Api\PaymentMethodManagement;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Rvvup\Payments\Gateway\Method;

class LimitCartExpressPayment
{
    /**
     * @param \Magento\Quote\Api\PaymentMethodManagementInterface $subject
     * @param \Magento\Quote\Api\Data\PaymentMethodInterface[] $result
     * @param int $cartId
     * @return \Magento\Quote\Api\Data\PaymentMethodInterface[]
     */
    public function afterGetList(PaymentMethodManagementInterface $subject, $result, $cartId)
    {
        // Do not limit if there is no payment set.
        try {
            $payment = $subject->get($cartId);

            if ($payment === null) {
                return $result;
            }
        } catch (NoSuchEntityException $ex) {
            return $result;
        }

        // Do not limit if we don't have an express payment method set.
        if ($payment->getadditionalInformation(Method::EXPRESS_PAYMENT_KEY) !== true) {
            return $result;
        }

        // Otherwise, filter it to limit to the express payment method.
        $filteredResult = array_filter($result, static function ($value) use ($payment) {
            return $value->getCode() === $payment->getMethod();
        });

        return array_values($filteredResult);
    }
}
