<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Exception;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\PaymentMethodsAssetsGetInterface;

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
     * @return array
     */
    public function execute(string $value, string $currency): array
    {
        $assets = [];

        try {
            foreach ($this->sdkProxy->getMethods($value, $currency) as $method) {
                if (!isset($method['name'])) {
                    continue;
                }

                $assets['rvvup_' . mb_strtolower($method['name'])] = $method['assets'] ?? [];
            }
        } catch (Exception $ex) {
            $this->logger->error(
                'Failed to load all the payment method assets with message: ' . $ex->getMessage(),
                [
                    'value' => $value,
                    'currency' => $currency
                ]
            );

            return $assets;
        }

        return $assets;
    }
}
