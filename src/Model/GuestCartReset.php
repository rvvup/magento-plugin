<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Rvvup\Payments\Api\CartResetInterface;
use Rvvup\Payments\Api\GuestCartResetInterface;

class GuestCartReset implements GuestCartResetInterface
{
    /**
     * @var \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var \Rvvup\Payments\Api\CartResetInterface
     */
    private $cartReset;

    /**
     * @param \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param \Rvvup\Payments\Api\CartResetInterface $cartReset
     * @return void
     */
    public function __construct(MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId, CartResetInterface $cartReset)
    {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->cartReset = $cartReset;
    }

    /**
     * Reset the data of the specified guest cart, empties items & addresses.
     *
     * @param string $cartId
     * @return string
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(string $cartId): string
    {
        $this->cartReset->execute((string) $this->maskedQuoteIdToQuoteId->execute($cartId));

        // This is the masked ID.
        return $cartId;
    }
}
