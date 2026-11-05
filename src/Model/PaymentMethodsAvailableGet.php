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
     * @var \Rvvup\Payments\Model\StorefrontRequestOptions|null
     */
    private $requestOptions;

    /**
     * @param \Rvvup\Payments\Model\SdkProxy $sdkProxy
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Rvvup\Payments\Model\StorefrontRequestOptions|null $requestOptions Set only for storefront widgets
     *     that must not wait on a slow API (see the Storefront* virtual types in di.xml). Leave null for checkout.
     * @return void
     */
    public function __construct(
        SdkProxy $sdkProxy,
        LoggerInterface $logger,
        ?StorefrontRequestOptions $requestOptions = null
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->logger = $logger;
        $this->requestOptions = $requestOptions;
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

            $inputOptions = $this->requestOptions === null ? null : $this->requestOptions->get();

            foreach ($this->sdkProxy->getMethods($value, $currency, $inputOptions) as $method) {
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
