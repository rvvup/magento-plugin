<?php

declare(strict_types=1);

namespace Rvvup\Payments\Observer\Quote\Model\Quote\Item;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote\Item;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\CartExpressPaymentRemoveInterface;
use Rvvup\Payments\Model\Logger;
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
     * @var \Psr\Log\LoggerInterface|Logger
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
        $quoteId = $this->getQuoteId($observer);

        if (!$quoteId) {
            return;
        }

        // Catch any errors not handled by service, so we don't interrupt customer journey.
        try {
            $this->cartExpressPaymentRemove->execute((string) $quoteId);
        } catch (Throwable $t) {
            $this->logger->error(
                'Could not remove quote express payment data on quote item saved with message: ' . $t->getMessage(),
                [
                    'quote_id' => $quoteId
                ]
            );
        }
    }

    private function getQuoteId($observer): ?string
    {
        /** @var Item|null $item */
        $item = $observer->getData('quote_item');

        if ($item !== null && $item->getQuoteId()) {
            return $item->getQuoteId();
        }

        if ($observer->getData('cart')) {
            return $observer->getData('cart')->getQuote()->getId();
        }

        if ($observer->getData('items')) {
            return $observer->getData('items')[0]->getQuote()->getId();
        }
        return null;
    }
}
