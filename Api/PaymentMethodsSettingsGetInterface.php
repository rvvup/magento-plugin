<?php

declare(strict_types=1);

namespace Rvvup\Payments\Api;

interface PaymentMethodsSettingsGetInterface
{
    /**
     * Get the settings for all/selected payment methods available for the value & currency.
     *
     * @param string $value
     * @param string $currency
     * @param array|string[] $methodCodes // Leave empty for all.
     * @return array
     */
    public function execute(string $value, string $currency, array $methodCodes = []): array;
}
