<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

interface IsPaymentMethodAvailableInterface
{
    /**
     * Check whether a payment method is available for the value & currency.
     *
     * @param string $methodCode
     * @param string $value
     * @param string $currency
     * @return bool
     */
    public function execute(string $methodCode, string $value, string $currency): bool;
}
