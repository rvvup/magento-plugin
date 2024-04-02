<?php

declare(strict_types=1);

namespace Rvvup\Payments\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Rvvup\Payments\Gateway\Method;

class Restore implements ObserverInterface
{
    private const PENDING = 'pending';

    /**
     * @var array
     */
    public $restrictedPaths = [];

    /**
     * @var SessionManagerInterface
     */
    public $session;

    /**
     * @param SessionManagerInterface $session
     * @param array $restrictedPaths
     */
    public function __construct(
        SessionManagerInterface $session,
        array $restrictedPaths
    ) {
        $this->session = $session;
        $this->restrictedPaths = $restrictedPaths;
    }

    /**
     * Restore customer quote
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $quote = $this->session->getQuote();

        if ($quote && !$quote->hasItems() && !$quote->getHasError()) {
            $order = $this->session->getLastRealOrder();

            if ($this->isOrderApplicable($order)) {
                $path = trim($observer->getRequest()->getOriginalPathInfo(), '/');
                if (!in_array($path, $this->restrictedPaths)) {
                    $this->session->restoreQuote();
                }
            }
        }
    }

    /**
     * @param OrderInterface $order
     * @return bool
     */
    private function isOrderApplicable(OrderInterface $order): bool
    {
        if ($order->getData() && $order->getStatus() == self::PENDING) {
            $paymentMethod = $order->getPayment()->getMethod();
            return strpos($paymentMethod, Method::PAYMENT_TITLE_PREFIX) === 0;
        }
        return  false;
    }
}
