<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Rvvup\Payments\Api\PaymentMethodsAssetsGetInterface;
use Rvvup\Payments\Api\PaymentMethodsSettingsGetInterface;

class PaymentMethodsAssetsGet implements PaymentMethodsAssetsGetInterface
{
    /**
     * @var \Rvvup\Payments\Api\PaymentMethodsSettingsGetInterface
     */
    private $paymentMethodsSettingsGet;

    /**
     * @param \Rvvup\Payments\Api\PaymentMethodsSettingsGetInterface $paymentMethodsSettingsGet
     * @return void
     */
    public function __construct(PaymentMethodsSettingsGetInterface $paymentMethodsSettingsGet)
    {
        $this->paymentMethodsSettingsGet = $paymentMethodsSettingsGet;
    }

    /**
     * Get the assets for all payment methods available for the value & currency.
     *
     * @param string $value
     * @param string $currency
     * @param array|string[] $methodCodes // Leave empty for all.
     * @return array
     */
    public function execute(string $value, string $currency, array $methodCodes = []): array
    {
        return array_map(static function ($methodSettings) {
            return $methodSettings['assets'] ?? [];
        }, $this->paymentMethodsSettingsGet->execute($value, $currency, $methodCodes));
    }
}
