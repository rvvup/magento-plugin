<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\PaymentActionInterface;
use Rvvup\Payments\Api\Data\PaymentActionInterfaceFactory;
use Rvvup\Payments\Api\GuestPaymentActionsGetInterface;
use Throwable;

class GuestPaymentActionsGet implements GuestPaymentActionsGetInterface
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
     * @var \Magento\Quote\Model\QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

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
     * @param \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param \Rvvup\Payments\Api\Data\PaymentActionInterfaceFactory $paymentActionInterfaceFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        OrderRepositoryInterface $orderRepository,
        PaymentActionInterfaceFactory $paymentActionInterfaceFactory,
        LoggerInterface $logger
    ) {
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->orderRepository = $orderRepository;
        $this->paymentActionInterfaceFactory = $paymentActionInterfaceFactory;
        $this->logger = $logger;
    }

    /**
     * Get the payment actions for the masked cart ID.
     *
     * @param string $cartId
     * @return \Rvvup\Payments\Api\Data\PaymentActionInterface[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(string $cartId): array
    {
        $order = $this->getOrderByMaskedQuoteId($cartId);
        $paymentActions = $this->getOrderPaymentActions($order, $cartId);

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
                'Error loading Payment Actions for guest user. Failed return result with message: ' . $t->getMessage(),
                [
                    'masked_quote_id' => $cartId,
                    'order_id' => $order->getEntityId(),
                    'payment_id' => $order->getPayment()->getEntityId() // Already checked not null
                ]
            );

            throw new LocalizedException(__('Something went wrong'));
        }

        if (empty($paymentActionsDataArray)) {
            $this->logger->error('Error loading Payment Actions for guest user. No payment actions found.', [
                'masked_quote_id' => $cartId,
                'order_id' => $order->getEntityId(),
                'payment_id' => $order->getPayment()->getEntityId() // Already checked not null
            ]);

            throw new LocalizedException(__('Something went wrong'));
        }

        return $paymentActionsDataArray;
    }

    /**
     * @param string $cartId
     * @return \Magento\Sales\Api\Data\OrderInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getOrderByMaskedQuoteId(string $cartId): OrderInterface
    {
        /** @var \Magento\Quote\Model\QuoteIdMask $quoteIdMask */
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');

        if ($quoteIdMask->getQuoteId() === null) {
            $this->logger->error('Error loading Payment Actions for guest user. No quote ID found.', [
                'masked_quote_id' => $cartId
            ]);

            throw new LocalizedException(__('Something went wrong'));
        }

        try {
            $sortOrder = $this->sortOrderBuilder->setDescendingDirection()
                ->setField('created_at')
                ->create();

            $searchCriteria = $this->searchCriteriaBuilder->setPageSize(1)
                ->addSortOrder($sortOrder)
                ->addFilter('quote_id', $quoteIdMask->getQuoteId())
                ->create();

            $result = $this->orderRepository->getList($searchCriteria);
        } catch (Exception $e) {
            $this->logger->error('Error loading Payment Actions for guest user with message: ' . $e->getMessage(), [
                'masked_quote_id' => $cartId
            ]);

            throw new LocalizedException(__('Something went wrong'));
        }

        $orders = $result->getItems();
        $order = reset($orders);

        if (!$order) {
            $this->logger->error('Error loading Payment Actions for guest user. No order found.', [
                'masked_quote_id' => $cartId
            ]);

            throw new LocalizedException(__('Something went wrong'));
        }

        return $order;
    }

    /**
     * Get the order payment's paymentActions from its additional information
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param string $cartId
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getOrderPaymentActions(OrderInterface $order, string $cartId): array
    {
        $payment = $order->getPayment();

        // Fail-safe, all orders should have an associated payment record
        if ($payment === null) {
            $this->logger->error('Error loading Payment Actions for guest user. No order payment found.', [
                'masked_quote_id' => $cartId,
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
                'Error loading Payment Actions for guest user. No order payment additional information found.',
                [
                    'masked_quote_id' => $cartId,
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
