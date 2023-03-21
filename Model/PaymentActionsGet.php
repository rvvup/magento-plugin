<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\PaymentActionInterface;
use Rvvup\Payments\Api\Data\PaymentActionInterfaceFactory;
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
     * @var SdkProxy
     */
    private SdkProxy $sdkProxy;

    /**
     * @var CommandPoolInterface
     */
    private CommandPoolInterface $commandPool;

    /**
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentActionInterfaceFactory $paymentActionInterfaceFactory
     * @param SdkProxy $sdkProxy
     * @param CommandPoolInterface $commandPool
     * @param LoggerInterface $logger
     */
    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder,
        OrderRepositoryInterface $orderRepository,
        PaymentActionInterfaceFactory $paymentActionInterfaceFactory,
        SdkProxy $sdkProxy,
        CommandPoolInterface $commandPool,
        LoggerInterface $logger
    ) {
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderRepository = $orderRepository;
        $this->paymentActionInterfaceFactory = $paymentActionInterfaceFactory;
        $this->sdkProxy = $sdkProxy;
        $this->commandPool = $commandPool;
        $this->logger = $logger;
    }

    /**
     * Get the payment actions for the cart ID & customer ID if provided.
     *
     * @param string $cartId
     * @param string|null $customerId
     * @return PaymentActionInterface[]
     * @throws LocalizedException
     */
    public function execute(string $cartId, ?string $customerId = null): array
    {
        $order = $this->getOrderByCartIdAndCustomerId($cartId, $customerId);

        $this->validate($order, $cartId, $customerId);

        if ($order->getPayment()->getAdditionalInformation('is_rvvup_express_payment')) {
            $paymentActions = $this->getExpressOrderPaymentActions($order);
        } else {
            $paymentActions = $this->createRvvupPayment($order);
        }

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
                    'quote_id' => $cartId,
                    'order_id' => $order->getEntityId(),
                    'customer_id' => $customerId
                ]
            );

            throw new LocalizedException(__('Something went wrong'));
        }

        if (empty($paymentActionsDataArray)) {
            $this->logger->error('Error loading Payment Actions for user. No payment actions found.', [
                'quote_id' => $cartId,
                'order_id' => $order->getEntityId(),
                'customer_id' => $customerId
            ]);

            throw new LocalizedException(__('Something went wrong'));
        }

        return $paymentActionsDataArray;
    }

    /**
     * @param string $cartId
     * @param string|null $customerId
     * @return OrderInterface
     * @throws LocalizedException
     */
    private function getOrderByCartIdAndCustomerId(string $cartId, ?string $customerId = null): OrderInterface
    {
        try {
            $sortOrder = $this->sortOrderBuilder->setDescendingDirection()
                ->setField('created_at')
                ->create();

            $this->searchCriteriaBuilder->setPageSize(1)
                ->addSortOrder($sortOrder)
                ->addFilter('quote_id', $cartId);

            // If customer ID is provided, pass it.
            if ($customerId !== null) {
                $this->searchCriteriaBuilder->addFilter('customer_id', $customerId);
            }

            $searchCriteria = $this->searchCriteriaBuilder->create();

            $result = $this->orderRepository->getList($searchCriteria);
        } catch (Exception $e) {
            $this->logger->error('Error loading Payment Actions for order with message: ' . $e->getMessage(), [
                'quote_id' => $cartId,
                'customer_id' => $customerId
            ]);

            throw new LocalizedException(__('Something went wrong'));
        }

        $orders = $result->getItems();
        $order = reset($orders);

        if (!$order) {
            $this->logger->error('Error loading Payment Actions. No order found.', [
                'quote_id' => $cartId,
                'customer_id' => $customerId
            ]);

            throw new LocalizedException(__('Something went wrong'));
        }

        return $order;
    }

    /**
     * Get the order payment's paymentActions from its additional information
     *
     * @param OrderInterface $order
     * @return array
     */
    private function getExpressOrderPaymentActions(OrderInterface $order): array
    {
        $id = $order->getPayment()->getAdditionalInformation('rvvup_order_id');
        $rvvupOrder = $this->sdkProxy->getOrder($id);

        if (!empty($rvvupOrder)) {
            return
                [
                    [
                        "type" => 'authorization',
                        "method" => 'redirect_url',
                        "value" => $rvvupOrder["redirectToCheckoutUrl"],
                    ],
                    [
                        "type" => 'cancel',
                        "method" => 'redirect_url',
                        "value" => $rvvupOrder['redirectToStoreUrl'],
                    ],
                ];
        }
        return [];
    }

    private function createRvvupPayment($order): array
    {
        $result = $this->commandPool->get('createPayment')->execute(['payment' => $order->getPayment()]);
        return $result['data']['paymentCreate']['summary']['paymentActions'];
    }

    /**
     * Create & return a PaymentActionInterface Data object.
     *
     * @param array $paymentAction
     * @return \Rvvup\Payments\Api\Data\PaymentActionInterface
     */
    private function getPaymentActionDataObject(array $paymentAction): PaymentActionInterface
    {
        /** @var \Rvvup\Payments\Api\Data\PaymentActionInterface $paymentActionData */
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

    /**
     * @param OrderInterface $order
     * @param string $cartId
     * @param string|null $customerId
     * @return void
     * @throws LocalizedException
     */
    private function validate(OrderInterface $order, string $cartId, ?string $customerId = null): void
    {
        $payment = $order->getPayment();

        // Fail-safe, all orders should have an associated payment record
        if ($payment === null) {
            $this->logger->error('Error loading Payment Actions for user. No order payment found.', [
                'quote_id' => $cartId,
                'order_id' => $order->getEntityId(),
                'customer_id' => $customerId,
            ]);

            throw new LocalizedException(__('Something went wrong'));
        }

        $paymentAdditionalInformation = $payment->getAdditionalInformation();

        if (!isset($paymentAdditionalInformation['rvvup_order_id'])) {
            $this->logger->error('Error loading Payment Actions. No order id additional information found.', [
                'quote_id' => $cartId,
                'order_id' => $order->getEntityId(),
                'payment_id' => $payment->getEntityId(),
                'customer_id' => $customerId
            ]);

            throw new LocalizedException(__('Something went wrong'));
        }
    }
}
