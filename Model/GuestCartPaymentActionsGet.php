<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Rvvup\Payments\Api\CartPaymentActionsGetInterface;
use Rvvup\Payments\Api\GuestCartPaymentActionsGetInterface;

class GuestCartPaymentActionsGet implements GuestCartPaymentActionsGetInterface
{
    /**
     * @var \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var \Rvvup\Payments\Api\CartPaymentActionsGetInterface
     */
    private $cartPaymentActionsGet;

    /**
     * @param \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param \Rvvup\Payments\Api\CartPaymentActionsGetInterface $cartPaymentActionsGet
     * @return void
     */
    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        CartPaymentActionsGetInterface $cartPaymentActionsGet
    ) {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->cartPaymentActionsGet = $cartPaymentActionsGet;
    }

    /**
     * @param string $cartId
     * @param bool $expressActions
     * @return \Rvvup\Payments\Api\Data\PaymentActionInterface[]
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(string $cartId, bool $expressActions = false): array
    {
        return $this->cartPaymentActionsGet->execute(
            (string) $this->maskedQuoteIdToQuoteId->execute($cartId),
            $expressActions
        );
    }
}
