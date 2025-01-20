<?php

declare(strict_types=1);

namespace Rvvup\Payments\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Payment;
use Rvvup\Payments\Gateway\Method;

class IsVoidable
{
    /**
     * @param Payment $subject
     * @param callable $proceed
     * @return bool
     * @throws LocalizedException
     */
    public function aroundCanVoid(Payment $subject, callable $proceed): bool
    {
        if (strpos($subject->getMethod(), Method::PAYMENT_TITLE_PREFIX) !== 0) {
            return $proceed();
        }
        return (bool)$subject->getMethodInstance()->canVoid();
    }
}
