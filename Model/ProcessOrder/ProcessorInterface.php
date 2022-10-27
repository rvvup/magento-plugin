<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\ProcessOrder;

use Magento\Sales\Api\Data\OrderInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterface;

interface ProcessorInterface
{
    /**
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param array $rvvupData
     * @return \Rvvup\Payments\Api\Data\ProcessOrderResultInterface
     */
    public function execute(OrderInterface $order, array $rvvupData): ProcessOrderResultInterface;
}
