<?php
declare(strict_types=1);

namespace Rvvup\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;

class Cancel extends AbstractDataAssignObserver
{

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $payment = $observer->getPayment();
        if ($payment->getMethod() == 'rvvup_payment-link') {
            $method = $payment->getMethodInstance();
            $method->setStore($payment->getOrder()->getStoreId());
            $method->cancel($payment);
        }
    }
}
