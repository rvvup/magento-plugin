<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\ProcessRefund;

use Magento\Sales\Api\Data\OrderInterface;

interface ProcessorInterface
{
    /**
     * @param OrderInterface $order
     * @param array $payload
     * @return void
     */
    public function execute(OrderInterface $order, array $payload): void;
}
