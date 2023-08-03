<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Psr\Log\LoggerInterface;
use Rvvup\Payments\Gateway\Method;

class IsPaymentMethodAvailable implements IsPaymentMethodAvailableInterface
{
    /**
     * @var \Rvvup\Payments\Model\PaymentMethodsAvailableGetInterface
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
    public function __construct(
        PaymentMethodsAvailableGetInterface $paymentMethodsAvailableGet,
        LoggerInterface $logger
    ) {
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
        $formattedMethodCode = mb_strtolower(Method::PAYMENT_TITLE_PREFIX . $methodCode);

        $result = array_key_exists($formattedMethodCode, $this->paymentMethodsAvailableGet->execute($value, $currency));

        if ($result) {
            return true;
        }

        return false;
    }
}
