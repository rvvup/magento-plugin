<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Rvvup\Payments\Api\PaymentMethodsSettingsGetInterface;
use Rvvup\Payments\Gateway\Method;

class PaymentMethodsSettingsGet implements PaymentMethodsSettingsGetInterface
{
    /**
     * @var \Rvvup\Payments\Model\PaymentMethodsAvailableGetInterface
     */
    private $paymentMethodsAvailableGet;

    /**
     * @param \Rvvup\Payments\Model\PaymentMethodsAvailableGetInterface $paymentMethodsAvailableGet
     * @return void
     */
    public function __construct(PaymentMethodsAvailableGetInterface $paymentMethodsAvailableGet)
    {
        $this->paymentMethodsAvailableGet = $paymentMethodsAvailableGet;
    }

    /**
     * Get the settings for all/selected payment methods available for the value & currency.
     *
     * @param string $value
     * @param string $currency
     * @param array|string[] $methodCodes // Leave empty for all.
     * @return array
     */
    public function execute(string $value, string $currency, array $methodCodes = []): array
    {
        $methods = $this->paymentMethodsAvailableGet->execute($value, $currency);

        if (empty($methodCodes)) {
            return $this->getSettingsArray($methods);
        }

        // Format the methodCodes to be in the same format as in getAvailable method array keys.
        $formattedMethodCodes = array_map(static function ($methodCodesValue) {
            return mb_strtolower(Method::PAYMENT_TITLE_PREFIX . $methodCodesValue);
        }, $methodCodes);

        // Get the methods with the matching array keys (we flip the formatted array values to keys for matching)
        $filteredRequestedMethods = array_intersect_key($methods, array_flip($formattedMethodCodes));

        return $this->getSettingsArray($filteredRequestedMethods);
    }

    /**
     * Return an array with preserved keys and settings value if present.
     *
     * @param array $methods
     * @return array
     */
    private function getSettingsArray(array $methods): array
    {
        return array_map(static function ($method) {
            return $method['settings'] ?? [];
        }, $methods);
    }
}
