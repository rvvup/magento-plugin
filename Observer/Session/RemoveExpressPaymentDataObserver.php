<?php

declare(strict_types=1);

namespace Rvvup\Payments\Observer\Session;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Rvvup\Payments\Api\CartExpressPaymentRemoveInterface;

class RemoveExpressPaymentDataObserver implements ObserverInterface
{
    /**
     * @var \Rvvup\Payments\Api\CartExpressPaymentRemoveInterface
     */
    private $cartExpressPaymentRemove;

    /**
     * @param \Rvvup\Payments\Api\CartExpressPaymentRemoveInterface $cartExpressPaymentRemove
     * @return void
     */
    public function __construct(CartExpressPaymentRemoveInterface $cartExpressPaymentRemove)
    {
        $this->cartExpressPaymentRemove = $cartExpressPaymentRemove;
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Quote\Api\Data\CartInterface $quote */
        $quote = $observer->getData('quote');

        if ($quote === null || !$quote->getId()) {
            return;
        }

        $this->cartExpressPaymentRemove->execute((string) $quote->getId());
    }
}
