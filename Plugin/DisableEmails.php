<?php
declare(strict_types=1);

namespace Rvvup\Payments\Plugin;

use Magento\Sales\Model\AdminOrder\Create;

class DisableEmails
{
    /**
     * @param Create $subject
     * @return array
     */
    public function beforeCreateOrder(Create $subject): array
    {
        $payment = $subject->getQuote()->getPayment();
        $rvvupAdminMethods = ['rvvup_payment-link', 'rvvup_virtual-terminal'];
        if (in_array($payment->getMethod(), $rvvupAdminMethods)) {
            if ($subject->getSendConfirmation()) {
                $subject->setSendConfirmation(false);
            }
        }
        return [];
    }
}
