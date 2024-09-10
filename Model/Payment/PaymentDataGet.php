<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Payment;

use Psr\Log\LoggerInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Api\ApiProxyFactory;
use Throwable;

class PaymentDataGet implements PaymentDataGetInterface
{
    /**
     * @var \Rvvup\Payments\Model\Api\ApiProxyFactory
     */
    private $apiProxyFactory;

    /**
     * @var \Psr\Log\LoggerInterface|RvvupLog
     */
    private $logger;

    /**
     * @param \Rvvup\Payments\Model\SdkProxy $sdkProxy
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    public function __construct(ApiProxyFactory $apiProxyFactory, LoggerInterface $logger)
    {
        $this->apiProxyFactory = $apiProxyFactory;
        $this->logger = $logger;
    }

    /**
     * Get the Rvvup payment data from the API by Rvvup order ID.
     *
     * @param string $rvvupId
     * @return array
     */
    public function execute(int $storeId, string $rvvupId): array
    {
        try {
            return $this->apiProxyFactory->forStore($storeId)->getOrder($rvvupId);
        } catch (Throwable $t) {
            $this->logger->error('Failed to get data from Rvvup for order id', [Method::ORDER_ID => $rvvupId]);
            return [];
        }
    }
}
