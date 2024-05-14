<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Payment;

use Psr\Log\LoggerInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\SdkProxy;
use Throwable;

class PaymentDataGet implements PaymentDataGetInterface
{
    /**
     * @var \Rvvup\Payments\Model\SdkProxy
     */
    private $sdkProxy;

    /**
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
     * Get the Rvvup payment data from the API by Rvvup order ID.
     *
     * @param string $rvvupId
     * @return array
     */
    public function execute(string $rvvupId): array
    {
        try {
            return $this->sdkProxy->getOrder($rvvupId);
        } catch (Throwable $t) {
            $this->logger->error('Failed to get data from Rvvup for order id', [Method::ORDER_ID => $rvvupId]);
            return [];
        }
    }
}
