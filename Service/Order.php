<?php

declare(strict_types=1);

namespace Rvvup\Payments\Service;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class Order
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var OrderPaymentRepositoryInterface
     */
    private $orderPaymentRepository;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param string $rvvupOrderId
     * @return OrderInterface
     */
    public function getOrderByRvvupId(string $rvvupOrderId): OrderInterface
    {
        // Saerch for the payment record by the Rvvup order ID which is stored in the credit card field.
        $searchCriteria = $this->searchCriteriaBuilder->addFilter(
            OrderPaymentInterface::CC_TRANS_ID,
            $rvvupOrderId
        )->create();

        $resultSet = $this->orderPaymentRepository->getList($searchCriteria);

        // We always expect 1 payment object for a Rvvup Order ID.
        if ($resultSet->getTotalCount() !== 1) {
            $this->logger->warning('Webhook error. Payment not found for order.', [
                'rvvup_order_id' => $rvvupOrderId,
                'payments_count' => $resultSet->getTotalCount()
            ]);
        }

        $payments = $resultSet->getItems();
        /** @var \Magento\Sales\Api\Data\OrderPaymentInterface $payment */
        $payment = reset($payments);
        return $this->orderRepository->get($payment->getParentId());
    }

    /**
     * @param CartInterface $quote
     * @return array
     */
    public function getAllOrdersByQuote(CartInterface $quote): array
    {
        if (!empty($quote->getEntityId())) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(OrderInterface::QUOTE_ID, $quote->getEntityId())->create();
            try {
                return $this->orderRepository->getList($searchCriteria)->getItems();
            } catch (\Exception $e) {
                return [];
            }
        }
        return [];
    }
}
