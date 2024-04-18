<?php
declare(strict_types=1);

namespace Rvvup\Payments\Block\Order\View;

use Magento\Framework\Exception\LocalizedException;
use \Magento\Sales\Block\Adminhtml\Order\View\Info as MagentoInfo;
use Rvvup\Payments\Model\RvvupConfigProvider;

class Info extends MagentoInfo
{
    /**
     * @return bool
     * @throws LocalizedException
     */
    public function isRvvupAdminOrder(): bool
    {
        if ($this->getOrder()) {
            $payment = $this->getOrder()->getPayment();
            if ($payment && $payment->getMethod() == RvvupConfigProvider::CODE) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string|null
     * @throws LocalizedException
     */
    public function getPaymentLink(): ?string
    {
        if ($this->isRvvupAdminOrder()) {
            $payment = $this->getOrder()->getPayment();
            $message = $payment->getAdditionalInformation('rvvup_payment_link_message');
            if ($message && is_string($message)) {
                return $message;
            }
        }

        return null;
    }
}
