<?php

declare(strict_types=1);

namespace Rvvup\Payments\Api;

interface PaymentMethodsAssetsGetInterface
{
    /**
     * Get the assets for all payment methods available for the value & currency.
     *
     * @param string $value
     * @param string $currency
     * @return array
     */
    public function execute(string $value, string $currency): array;
}
