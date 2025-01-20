<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Exception;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Gateway\Method;

class PaymentMethodsAvailableGet implements PaymentMethodsAvailableGetInterface
{
    /**
     * @var \Rvvup\Payments\Model\SdkProxy
     */
    private $sdkProxy;

    /**
     * @var \Psr\Log\LoggerInterface
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
     * Get all available payment methods.
     *
     * @param string $value
     * @param string $currency
     * @return array
     */
    public function execute(string $value, string $currency): array
    {
        try {
            $methods = [];

            foreach ($this->sdkProxy->getMethods($value, $currency) as $method) {
                $methods[mb_strtolower(Method::PAYMENT_TITLE_PREFIX . $method['name'])] = $method;
            }

            return $methods;
        } catch (Exception $ex) {
            $this->logger->error('Failed to load all available payment methods with message: ' . $ex->getMessage(), [
                'value' => $value,
                'currency' => $currency
            ]);

            return [];
        }
    }
}
