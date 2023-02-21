<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Quote\Api\CartRepositoryInterface;
use Rvvup\Payments\Api\CartResetInterface;

class CartReset implements CartResetInterface
{
    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
     */
    public function __construct(CartRepositoryInterface $cartRepository)
    {
        $this->cartRepository = $cartRepository;
    }

    /**
     * Reset the data of the specified cart, empties items & addresses.
     *
     * @param string $cartId
     * @return string
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(string $cartId): string
    {
        /** @var \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote $quote */
        $quote = $this->cartRepository->getActive((int) $cartId);

        $quote->removeAllItems();

        $this->cartRepository->save($quote);

        return (string) $quote->getId();
    }
}
