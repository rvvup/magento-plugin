<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Payment;

use Magento\Quote\Api\Data\CartInterface;

interface PaymentCreateExpressInterface
{
    /**
     * Instantiate (create) a Rvvup Express Payment through the API
     *
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @param string $methodCode
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Rvvup\Payments\Exception\PaymentValidationException
     */
    public function execute(CartInterface $quote, string $methodCode): bool;
}
