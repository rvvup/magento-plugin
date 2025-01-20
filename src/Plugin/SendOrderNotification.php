<?php declare(strict_types=1);

namespace Rvvup\Payments\Plugin;

use Magento\Framework\Event\Observer;
use Magento\Quote\Observer\SubmitObserver;
use Magento\Sales\Model\Order;
use Rvvup\Payments\Gateway\Method;

/**
 * Send admin order confirmation
 */
class SendOrderNotification
{
    /**
     * Disable sending emails on Order Creation for Rvvup Orders
     * @param SubmitObserver $subject
     * @param Observer $observer
     * @return Observer[]
     */
    public function beforeExecute(SubmitObserver $subject, Observer $observer): array
    {
        /** @var  Order $order */
        $order = $observer->getEvent()->getOrder();
        if ($order) {
            $method = $order->getPayment()->getMethod();
            if (str_starts_with($method, Method::PAYMENT_TITLE_PREFIX)) {
                $order->setCanSendNewEmailFlag(false);
            }
        }

        return [$observer];
    }
}
