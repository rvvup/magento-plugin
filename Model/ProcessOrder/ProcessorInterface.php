<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\ProcessOrder;

use Magento\Sales\Api\Data\OrderInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterface;

interface ProcessorInterface
{
    /**
     * @param OrderInterface $order
     * @param array $rvvupData
     * @param string $origin
     * @return ProcessOrderResultInterface
     */
    public function execute(OrderInterface $order, array $rvvupData, string $origin): ProcessOrderResultInterface;
}
