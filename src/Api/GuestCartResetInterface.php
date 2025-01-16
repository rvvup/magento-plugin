<?php

declare(strict_types=1);

namespace Rvvup\Payments\Api;

interface GuestCartResetInterface
{
    /**
     * Reset the data of the specified guest cart, empties items & addresses.
     *
     * @param string $cartId
     * @return string
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(string $cartId): string;
}
