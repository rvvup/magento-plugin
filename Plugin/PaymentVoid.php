<?php
declare(strict_types=1);

namespace Rvvup\Payments\Plugin;

use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Payment;
use Rvvup\Payments\Model\RvvupConfigProvider;

class PaymentVoid
{
    /** @var ManagerInterface */
    private $eventManager;

    /**
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        ManagerInterface $eventManager
    ) {
        $this->eventManager = $eventManager;
    }

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

    public function aroundVoid(Payment $subject, callable $proceed, DataObject $document): Payment
    {
        $methodName = $subject->getMethod();
        if (strpos($methodName, RvvupConfigProvider::CODE) !== 0) {
            return $proceed();
        }
        $order = $subject->getOrder();
        $method = $subject->getMethodInstance();
        $method->setStore($order->getStoreId());
        $method->void($subject);
        $message =  __('Voided authorization for Rvvup Payment link');
        $order->addCommentToStatusHistory($message);
        $this->eventManager->dispatch('sales_order_payment_void', ['payment' => $this, 'invoice' => $document]);
        return $subject;
    }
}
