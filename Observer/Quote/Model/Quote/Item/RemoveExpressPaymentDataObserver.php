<?php

declare(strict_types=1);

namespace Rvvup\Payments\Observer\Quote\Model\Quote\Item;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\CartExpressPaymentRemoveInterface;
use Throwable;

class RemoveExpressPaymentDataObserver implements ObserverInterface
{
    /**
     * @var \Rvvup\Payments\Api\CartExpressPaymentRemoveInterface
     */
    private $cartExpressPaymentRemove;

    /**
     * Set via etc/di.xml
     *
     * @var \Psr\Log\LoggerInterface|RvvupLog
     */
    private $logger;

    /**
     * @param \Rvvup\Payments\Api\CartExpressPaymentRemoveInterface $cartExpressPaymentRemove
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    public function __construct(CartExpressPaymentRemoveInterface $cartExpressPaymentRemove, LoggerInterface $logger)
    {
        $this->cartExpressPaymentRemove = $cartExpressPaymentRemove;
        $this->logger = $logger;
    }
    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Quote\Model\Quote\Item|null $item */
        $item = $observer->getData('item');

        // No action if we don't have an item (should not happen as this is an event targeting items) or a quote ID.
        if ($item === null || !$item->getQuoteId()) {
            return;
        }

        // Catch any errors not handled by service, so we don't interrupt customer journey.
        try {
            $this->cartExpressPaymentRemove->execute((string) $item->getQuoteId());
        } catch (Throwable $t) {
            $this->logger->error(
                'Could not remove quote express payment data on quote item saved with message: ' . $t->getMessage(),
                [
                    'quote_id' => $item->getQuoteId()
                ]
            );
        }
    }
}
