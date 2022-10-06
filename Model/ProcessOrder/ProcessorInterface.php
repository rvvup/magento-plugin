<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\ProcessOrder;

use Magento\Framework\Controller\Result\Redirect;
use Magento\Sales\Api\Data\OrderInterface;

interface ProcessorInterface
{
    /**
     * @param OrderInterface $order
     * @param array $rvvupData
     * @param Redirect $redirect
     * @return mixed
     */
    public function execute(OrderInterface $order, array $rvvupData, Redirect $redirect): void;
}
