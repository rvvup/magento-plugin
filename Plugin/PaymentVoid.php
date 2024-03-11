<?php

namespace Rvvup\Payments\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Payment;
use Rvvup\Payments\Model\RvvupConfigProvider;

class PaymentVoid
{
    /**
     * @param Payment $subject
     * @param callable $proceed
     * @return bool
     * @throws LocalizedException
     */
    public function aroundCanVoid(Payment $subject, callable $proceed): bool
    {
        $methodName = $subject->getMethod();
        if (strpos($methodName, RvvupConfigProvider::CODE) !== 0) {
            return $proceed();
        }
        return (bool)$subject->getMethodInstance()->canVoid();
    }
}
