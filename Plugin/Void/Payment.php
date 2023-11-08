<?php

declare(strict_types=1);

namespace Rvvup\Payments\Plugin\Void;

use Magento\Framework\DataObject;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Rvvup\Payments\Gateway\Method;

class Payment
{

    /** @var OrderRepositoryInterface  */
    private $orderRepository;

    /**
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param \Magento\Sales\Model\Order\Payment $subject
     * @param $result
     * @param DataObject $document
     * @return mixed
     */
    public function afterVoid(\Magento\Sales\Model\Order\Payment $subject, $result, DataObject $document)
    {
        $order = $subject->getOrder();
        if (strpos($subject->getMethod(), Method::PAYMENT_TITLE_PREFIX) === 0) {
            /** Cancel order */
            $state = $order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING);
            $order->setStatus(Order::STATE_CANCELED)->setState($state);
            $this->orderRepository->save($order);
        }

        return $result;
    }
}
