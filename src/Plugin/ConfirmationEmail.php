<?php

declare(strict_types=1);

namespace Rvvup\Payments\Plugin;

use Magento\Sales\Block\Adminhtml\Order\Create\Totals;

class ConfirmationEmail
{
    /**
     * @param Totals $subject
     * @param bool $result
     * @return bool
     */
    public function afterCanSendNewOrderConfirmationEmail(Totals $subject, bool $result): bool
    {
        $quote = $subject->getQuote();
        $rvvupAdminMethods = ['rvvup_payment-link', 'rvvup_virtual-terminal'];
        if ($quote->getPayment()) {
            if (in_array($quote->getPayment()->getMethod(), $rvvupAdminMethods)) {
                return false;
            }
        }
        return $result;
    }
}
