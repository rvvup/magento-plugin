<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Psr\Log\LoggerInterface;

class IsPaymentMethodAvailable implements IsPaymentMethodAvailableInterface
{
    /**
     * @var \Rvvup\Payments\Model\PaymentMethodsAvailableGet
     */
    private $paymentMethodsAvailableGet;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @param \Rvvup\Payments\Model\PaymentMethodsAvailableGet $paymentMethodsAvailableGet
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    public function __construct(PaymentMethodsAvailableGet $paymentMethodsAvailableGet, LoggerInterface $logger)
    {
        $this->paymentMethodsAvailableGet = $paymentMethodsAvailableGet;
        $this->logger = $logger;
    }

    /**
     * Check whether a payment method is available for the value & currency.
     *
     * @param string $methodCode
     * @param string $value
     * @param string $currency
     * @return bool
     */
    public function execute(string $methodCode, string $value, string $currency): bool
    {
        // We need a numeric value, so false if not such.
        if (!is_numeric($value)) {
            return false;
        }

        $lowerCaseMethodName = mb_strtolower($methodCode);

        foreach ($this->paymentMethodsAvailableGet->execute($value, $currency) as $method) {
            if (!isset($method['name'])) {
                continue;
            }

            if (mb_strtolower($method['name']) === $lowerCaseMethodName) {
                return true;
            }
        }

        // Log debug & Default to false if not found.
        $this->logger->debug('Rvvup payment method is not available', [
            'method_code' => $lowerCaseMethodName,
            'value' => $value,
            'currency' => $currency
        ]);

        return false;
    }
}
