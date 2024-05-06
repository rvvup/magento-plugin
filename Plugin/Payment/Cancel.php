<?php
declare(strict_types=1);

namespace Rvvup\Payments\Plugin\Payment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Payment;

class Cancel
{
    /**
     * @param Payment $subject
     * @param callable $proceed
     * @return mixed
     * @throws LocalizedException
     */
    public function aroundCancel(Payment $subject, callable $proceed)
    {
        if ($subject->getMethod() == 'rvvup_payment-link') {
            $method = $subject->getMethodInstance();
            $method->setStore($subject->getOrder()->getStoreId());
            $method->cancel($subject);
        }
        return $proceed();
    }
}
