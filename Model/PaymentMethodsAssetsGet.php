<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Exception;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\PaymentMethodsAssetsGetInterface;
use Rvvup\Payments\Gateway\Method;

class PaymentMethodsAssetsGet implements PaymentMethodsAssetsGetInterface
{
    /**
     * @var \Rvvup\Payments\Model\SdkProxy
     */
    private $sdkProxy;

    /**
     * Set via di.xml
     *
     * @var \Psr\Log\LoggerInterface|RvvupLog
     */
    private $logger;

    /**
     * @param \Rvvup\Payments\Model\SdkProxy $sdkProxy
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    public function __construct(SdkProxy $sdkProxy, LoggerInterface $logger)
    {
        $this->sdkProxy = $sdkProxy;
        $this->logger = $logger;
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
        $assets = [];

        $loadAll = empty($methodCodes);

        try {
            foreach ($this->sdkProxy->getMethods($value, $currency) as $method) {
                if (!isset($method['name']) || !is_string($method['name'])) {
                    continue;
                }

                $methodName = mb_strtolower($method['name']);

                // If we wanted to load specific methods, check if method is one of the requested.
                if (!$loadAll && !in_array($methodName, $methodCodes, true)) {
                    continue;
                }

                $assets[Method::PAYMENT_TITLE_PREFIX . $methodName] = $method['settings']['assets'] ?? [];
            }
        } catch (Exception $ex) {
            $this->logger->error(
                'Failed to load the payment method assets with message: ' . $ex->getMessage(),
                [
                    'value' => $value,
                    'currency' => $currency,
                    'method_codes' => $methodCodes
                ]
            );

            return $assets;
        }

        return $assets;
    }
}
