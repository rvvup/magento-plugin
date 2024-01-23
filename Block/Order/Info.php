<?php

declare(strict_types=1);

namespace Rvvup\Payments\Block\Order;

use Magento\Framework\Exception\LocalizedException;

class Info extends \Magento\Sales\Block\Order\Info
{
    /**
     * @inheritDoc
     * @return void
     * @throws LocalizedException
     */
    protected function _prepareLayout(): void
    {
        $this->pageConfig->getTitle()->set(__('Order # %1', $this->getOrder()->getRealOrderId()));
        $payment = $this->getOrder()->getPayment();
        if ($payment) {
            $infoBlock = $this->paymentHelper->getInfoBlock($payment, $this->getLayout());
            $this->setChild('payment_info', $infoBlock);
        }
    }
}
