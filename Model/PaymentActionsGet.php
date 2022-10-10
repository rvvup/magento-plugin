<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\PaymentActionInterface;
use Rvvup\Payments\Api\Data\PaymentActionInterfaceFactory;
use Rvvup\Payments\Api\PaymentActionsGetInterface;
use Throwable;

class PaymentActionsGet implements PaymentActionsGetInterface
{
    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var \Magento\Framework\Api\SortOrderBuilder
     */
    private $sortOrderBuilder;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var \Rvvup\Payments\Api\Data\PaymentActionInterfaceFactory
     */
    private $paymentActionInterfaceFactory;

    /**
     * Set via di.xml
     *
     * @var \Psr\Log\LoggerInterface|RvvupLog
     */
    private $logger;

    /**
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\Framework\Api\SortOrderBuilder $sortOrderBuilder
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Rvvup\Payments\Api\Data\PaymentActionInterfaceFactory $paymentActionInterfaceFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder,
        OrderRepositoryInterface $orderRepository,
        PaymentActionInterfaceFactory $paymentActionInterfaceFactory,
        LoggerInterface $logger
    ) {
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderRepository = $orderRepository;
        $this->paymentActionInterfaceFactory = $paymentActionInterfaceFactory;
        $this->logger = $logger;
    }

    /**
     * Get the payment actions for the customer ID & cart ID.
     *
     * @param string $customerId
     * @param string $cartId
     * @return PaymentActionInterface[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(string $customerId, string $cartId): array
    {
        $order = $this->getOrderByCustomerIdAndQuoteId($customerId, $cartId);
        $paymentActions = $this->getOrderPaymentActions($order, $customerId, $cartId);

        $paymentActionsDataArray = [];

        try {
            foreach ($paymentActions as $paymentAction) {
                if (!is_array($paymentAction)) {
                    continue;
                }

                $paymentActionData = $this->getPaymentActionDataObject($paymentAction);

                // Validate all all properties have values.
                if ($paymentActionData->getType() !== null
                    && $paymentActionData->getMethod() !== null
                    && $paymentActionData->getValue() !== null
                ) {
                    $paymentActionsDataArray[] = $paymentActionData;
                }
            }
        } catch (Throwable $t) {
            $this->logger->error(
                'Error loading Payment Actions for user. Failed return result with message: ' . $t->getMessage(),
                [
                    'masked_quote_id' => $cartId,
                    'customer_id' => $customerId,
                    'order_id' => $order->getEntityId(),
                    'payment_id' => $order->getPayment()->getEntityId() // Already checked not null
                ]
            );

            throw new LocalizedException(__('Something went wrong'));
        }

        if (empty($paymentActionsDataArray)) {
            $this->logger->error('Error loading Payment Actions for user. No payment actions found.', [
                'masked_quote_id' => $cartId,
                'customer_id' => $customerId,
                'order_id' => $order->getEntityId(),
                'payment_id' => $order->getPayment()->getEntityId() // Already checked not null
            ]);

            throw new LocalizedException(__('Something went wrong'));
        }

        return $paymentActionsDataArray;
    }

    /**
     * @param string $customerId
     * @param string $cartId
     * @return \Magento\Sales\Api\Data\OrderInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getOrderByCustomerIdAndQuoteId(string $customerId, string $cartId): OrderInterface
    {
        try {
            $sortOrder = $this->sortOrderBuilder->setDescendingDirection()
                ->setField('created_at')
                ->create();

            $searchCriteria = $this->searchCriteriaBuilder->setPageSize(1)
                ->addSortOrder($sortOrder)
                ->addFilter('customer_id', $customerId)
                ->addFilter('quote_id', $cartId)
                ->create();

            $result = $this->orderRepository->getList($searchCriteria);
        } catch (Exception $e) {
            $this->logger->error('Error loading Payment Actions for user with message: ' . $e->getMessage(), [
                'customer_id' => $customerId,
                'quote_id' => $cartId
            ]);

            throw new LocalizedException(__('Something went wrong'));
        }

        $orders = $result->getItems();
        $order = reset($orders);

        if (!$order) {
            $this->logger->error('Error loading Payment Actions for user. No order found.', [
                'customer_id' => $customerId,
                'quote_id' => $cartId
            ]);

            throw new LocalizedException(__('Something went wrong'));
        }

        return $order;
    }

    /**
     * Get the order payment's paymentActions from its additional information
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param string $customerId
     * @param string $cartId
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getOrderPaymentActions(OrderInterface $order, string $customerId, string $cartId): array
    {
        $payment = $order->getPayment();

        // Fail-safe, all orders should have an associated payment record
        if ($payment === null) {
            $this->logger->error('Error loading Payment Actions for user. No order payment found.', [
                'customer_id' => $customerId,
                'quote_id' => $cartId,
                'order_id' => $order->getEntityId()
            ]);

            throw new LocalizedException(__('Something went wrong'));
        }

        $paymentAdditionalInformation = $payment->getAdditionalInformation();

        // Check if payment actions are set as array & not empty
        if (empty($paymentAdditionalInformation['paymentActions'])
            || !is_array($paymentAdditionalInformation['paymentActions'])
        ) {
            $this->logger->error(
                'Error loading Payment Actions for user. No order payment additional information found.',
                [
                    'customer_id' => $customerId,
                    'quote_id' => $cartId,
                    'order_id' => $order->getEntityId(),
                    'payment_id' => $payment->getEntityId()
                ]
            );

            throw new LocalizedException(__('Something went wrong'));
        }

        return $paymentAdditionalInformation['paymentActions'];
    }

    /**
     * Create & return a PaymentActionInterface Data object.
     *
     * @param array $paymentAction
     * @return \Rvvup\Payments\Api\Data\PaymentActionInterface
     */
    private function getPaymentActionDataObject(array $paymentAction): PaymentActionInterface
    {
        /** @var PaymentActionInterface $paymentActionData */
        $paymentActionData = $this->paymentActionInterfaceFactory->create();

        if (isset($paymentAction['type'])) {
            $paymentActionData->setType(mb_strtolower($paymentAction['type']));
        }

        if (isset($paymentAction['method'])) {
            $paymentActionData->setMethod(mb_strtolower($paymentAction['method']));
        }

        if (isset($paymentAction['value'])) {
            // Don't lowercase value.
            $paymentActionData->setValue($paymentAction['value']);
        }

        return $paymentActionData;
    }
}
