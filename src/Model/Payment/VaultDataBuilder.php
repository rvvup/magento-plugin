<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Payment;

use Magento\Payment\Model\InfoInterface;
use Rvvup\Payments\Gateway\Method;

class VaultDataBuilder
{
    /**
     * Applies saveTokenScope to the payment session input when the shopper opted in and is logged in.
     * Also marks the payment so the post-payment handler knows a token was requested.
     */
    public function build(VaultPaymentSessionCreateInput $input, InfoInterface $payment, int $customerId): void
    {
        if (!$payment->getAdditionalInformation(Method::SAVE_PAYMENT_METHOD) || $customerId === 0) {
            return;
        }

        $input->setSaveTokenScope('CUSTOMER');
        $payment->setAdditionalInformation(Method::SAVE_TOKEN_REQUESTED, '1');
    }
}
