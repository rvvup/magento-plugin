<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\PaymentActionInterfaceFactory;
use Rvvup\Payments\Api\GuestPaymentActionsGetInterface;

class GuestPaymentActionsGet implements GuestPaymentActionsGetInterface
{
    /**
     * @var \Magento\Quote\Model\QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var \Rvvup\Payments\Model\PaymentActionsGetInterface
     */
    private $paymentActionsGet;

    /**
     * Set via di.xml
     *
     * @var \Psr\Log\LoggerInterface|RvvupLog
     */
    private $logger;

    /**
     * @param \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory
     * @param \Rvvup\Payments\Model\PaymentActionsGetInterface $paymentActionsGet
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        PaymentActionsGetInterface $paymentActionsGet,
        LoggerInterface $logger
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->paymentActionsGet = $paymentActionsGet;
        $this->logger = $logger;
    }

    /**
     * Get the payment actions for the masked cart ID.
     *
     * @param string $cartId
     * @return \Rvvup\Payments\Api\Data\PaymentActionInterface[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(string $cartId): array
    {
        /** @var \Magento\Quote\Model\QuoteIdMask $quoteIdMask */
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id'); // @phpstan-ignore-line

        if ($quoteIdMask->getQuoteId() === null) {
            $this->logger->error('Error loading Payment Actions for guest. No quote ID found.', [
                'masked_quote_id' => $cartId
            ]);

            throw new LocalizedException(__('Something went wrong'));
        }

        return $this->paymentActionsGet->execute($quoteIdMask->getQuoteId());
    }
}
