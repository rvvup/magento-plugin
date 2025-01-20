<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

interface PaymentMethodsAvailableGetInterface
{
    /**
     * Get all available payment methods.
     *
     * @param string $value
     * @param string $currency
     * @return array
     */
    public function execute(string $value, string $currency): array;
}
