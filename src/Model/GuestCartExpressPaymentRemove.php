<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Rvvup\Payments\Api\CartExpressPaymentRemoveInterface;
use Rvvup\Payments\Api\GuestCartExpressPaymentRemoveInterface;

class GuestCartExpressPaymentRemove implements GuestCartExpressPaymentRemoveInterface
{
    /**
     * @var \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;
    /**
     * @var \Rvvup\Payments\Api\CartExpressPaymentRemoveInterface
     */
    private $cartExpressPaymentRemove;

    /**
     * @param \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param \Rvvup\Payments\Api\CartExpressPaymentRemoveInterface $cartExpressPaymentRemove
     * @return void
     */
    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        CartExpressPaymentRemoveInterface $cartExpressPaymentRemove
    ) {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->cartExpressPaymentRemove = $cartExpressPaymentRemove;
    }

    /**
     * Remove the payment data of express payment for the specified cart & rvvup payment method for a guest user.
     *
     * @param string $cartId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(string $cartId): bool
    {
        return $this->cartExpressPaymentRemove->execute((string) $this->maskedQuoteIdToQuoteId->execute($cartId));
    }
}
