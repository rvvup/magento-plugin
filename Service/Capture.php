<?php

declare(strict_types=1);

namespace Rvvup\Payments\Service;

use Magento\Checkout\Helper\Data;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use Magento\Quote\Model\QuoteManagement;
use Magento\Quote\Model\ResourceModel\Quote\Payment\Collection;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\OrderPaymentSearchResultInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderIncrementIdChecker;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\ValidationInterface;
use Rvvup\Payments\Api\Data\ValidationInterfaceFactory;
use Rvvup\Payments\Exception\PaymentValidationException;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\SdkProxy;
use Magento\Quote\Model\ResourceModel\Quote\Payment\CollectionFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;

class Capture
{
    /** Set via di.xml
     * @var LoggerInterface
     */
    private $logger;

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /** @var OrderPaymentRepositoryInterface */
    private $orderPaymentRepository;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var QuoteResource */
    private $quoteResource;

    /** @var QuoteManagement */
    private $quoteManagement;

    /** @var SdkProxy */
    private $sdkProxy;

    /** @var CollectionFactory */
    private $collectionFactory;

    /** @var CartRepositoryInterface */
    private $cartRepository;

    /** @var Data */
    private $checkoutHelper;

    /** @var OrderIncrementIdChecker */
    private $orderIncrementChecker;

    /** @var ValidationInterface  */
    private $validationInterface;

    /** @var ValidationInterfaceFactory  */
    private $validationInterfaceFactory;

    /** @var OrderInterface */
    private $order;

    /**
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param QuoteResource $quoteResource
     * @param QuoteManagement $quoteManagement
     * @param SdkProxy $sdkProxy
     * @param CollectionFactory $collectionFactory
     * @param CartRepositoryInterface $cartRepository
     * @param Data $checkoutHelper
     * @param OrderIncrementIdChecker $orderIncrementIdChecker
     * @param ValidationInterface $validationInterface
     * @param ValidationInterfaceFactory $validationInterfaceFactory
     * @param OrderInterface $order
     * @param LoggerInterface $logger
     */
    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        OrderRepositoryInterface $orderRepository,
        QuoteResource $quoteResource,
        QuoteManagement $quoteManagement,
        SdkProxy $sdkProxy,
        CollectionFactory $collectionFactory,
        CartRepositoryInterface $cartRepository,
        Data $checkoutHelper,
        OrderIncrementIdChecker $orderIncrementIdChecker,
        ValidationInterface $validationInterface,
        ValidationInterfaceFactory $validationInterfaceFactory,
        OrderInterface $order,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->orderRepository = $orderRepository;
        $this->quoteResource = $quoteResource;
        $this->quoteManagement = $quoteManagement;
        $this->sdkProxy = $sdkProxy;
        $this->collectionFactory = $collectionFactory;
        $this->cartRepository = $cartRepository;
        $this->checkoutHelper = $checkoutHelper;
        $this->orderIncrementChecker = $orderIncrementIdChecker;
        $this->validationInterface = $validationInterface;
        $this->order = $order;
        $this->validationInterfaceFactory = $validationInterfaceFactory;
    }

    /**
     * @param string $rvvupOrderId
     * @param int|null $storeId
     * @return OrderInterface|null
     * @throws PaymentValidationException
     */
    public function getOrderByRvvupId(string $rvvupOrderId, int $storeId = null): ?OrderInterface
    {
        $resultSet = $this->getOrderListByRvvupId($rvvupOrderId);

        // We always expect 1 payment object for a Rvvup Order ID.
        if ($resultSet->getTotalCount() !== 1) {
            $this->logger->warning('Webhook error. Payment not found for order.', [
                Method::ORDER_ID => $rvvupOrderId,
                'payments_count' => $resultSet->getTotalCount()
            ]);
            throw new PaymentValidationException(__('Error finding order with rvvup_id ' . $rvvupOrderId));
        }

        $payments = $resultSet->getItems();
        /** @var OrderPaymentInterface $payment */
        $payment = reset($payments);
        $order = $this->orderRepository->get($payment->getParentId());

        if ($storeId && $order->getStoreId() !== $storeId) {
            $this->logger->warning('Webhook log. Payment not found for an order in specified store', [
                Method::ORDER_ID => $rvvupOrderId,
                'store_id' => $storeId,
                'payments_count' => $resultSet->getTotalCount()
            ]);
            return null;
        }

        return $order;
    }

    /**
     * @param string $rvvupOrderId
     * @return OrderPaymentSearchResultInterface
     */
    public function getOrderListByRvvupId(string $rvvupOrderId): OrderPaymentSearchResultInterface
    {
        // Search for the payment record by the Rvvup order ID
        $searchCriteria = $this->searchCriteriaBuilder->addFilter(
            'additional_information',
            '%' . $rvvupOrderId . '%',
            'like'
        )->create();
        return $this->orderPaymentRepository->getList($searchCriteria);
    }

    /**
     * @param Quote|null $quote
     * @param string|null $rvvupId
     * @param string|null $paymentStatus
     * @param string|null $origin
     * @return ValidationInterface
     */
    public function validate(
        ?Quote &$quote,
        string $rvvupId = null,
        string $paymentStatus = null,
        string $origin = null
    ): ValidationInterface {
        return $this->validationInterface->validate($quote, $rvvupId, $paymentStatus, $origin);
    }

    /**
     * @param string $rvvupId
     * @param Quote $quote
     * @param string $origin
     * @return ValidationInterface
     */
    public function createOrder(string $rvvupId, Quote $quote, string $origin): ValidationInterface
    {
        $this->quoteResource->beginTransaction();
        $payment = $quote->getPayment();

        try {
            if ($this->orderIncrementChecker->isIncrementIdUsed($quote->getReservedOrderId())) {
                return $this->validationInterfaceFactory->create(
                    [
                        'data' => [
                            ValidationInterface::ORDER_ID => $quote->getReservedOrderId(),
                            ValidationInterface::ALREADY_EXISTS => true]
                    ]
                );
            }

            $orderId = $this->quoteManagement->placeOrder($quote->getEntityId(), $payment);
            $this->quoteResource->commit();
            return $this->validationInterfaceFactory->create(
                [
                    'data' => [
                        ValidationInterface::ORDER_ID => $orderId,
                        ValidationInterface::ALREADY_EXISTS => false
                    ]
                ]
            );
        } catch (NoSuchEntityException $e) {
            $this->quoteResource->rollback();
            return $this->validationInterfaceFactory->create(
                [
                    'data' =>
                        [
                            ValidationInterface::ORDER_ID => $quote->getReservedOrderId(),
                            ValidationInterface::ALREADY_EXISTS => true
                        ]
                ]
            );
        } catch (\Exception $e) {
            $this->quoteResource->rollback();
            if (str_contains($e->getMessage(), AdapterInterface::ERROR_ROLLBACK_INCOMPLETE_MESSAGE)) {
                return $this->validationInterfaceFactory->create(
                    [
                        'data' => [
                            ValidationInterface::ORDER_ID => $quote->getReservedOrderId(),
                            ValidationInterface::ALREADY_EXISTS => true
                        ]
                    ]
                );
            }
            $this->logger->addRvvupError(
                'Order placement within rvvup payment failed',
                $e->getMessage(),
                $rvvupId,
                null,
                $quote->getReservedOrderId(),
                $origin
            );
            return $this->validationInterfaceFactory->create(
                [
                    'data' => [
                        ValidationInterface::ORDER_ID => false,
                        ValidationInterface::ALREADY_EXISTS => false
                    ]
                ]
            );
        }
    }

    /**
     * @param Payment $payment
     * @param string $lastTransactionId
     * @param string $rvvupPaymentId
     * @param string $rvvupId
     * @param string $origin
     * @return bool
     */
    public function paymentCapture(
        Quote\Payment $payment,
        string $lastTransactionId,
        string $rvvupPaymentId,
        string $rvvupId,
        string $origin
    ): bool {
        try {
            if ($payment->getMethodInstance()->getCaptureType() !== 'MANUAL') {
                $this->sdkProxy->paymentCapture($lastTransactionId, $rvvupPaymentId);
            }
        } catch (\Exception $e) {
            $this->logger->addRvvupError(
                'Rvvup order capture failed during payment capture',
                $e->getMessage(),
                $rvvupId,
                $rvvupPaymentId,
                null,
                $origin
            );
            return false;
        }
        return true;
    }

    /**
     * @param string $rvvupId
     * @param string|null $storeId
     * @return Quote|null
     */
    public function getQuoteByRvvupId(string $rvvupId, string $storeId = null): ?Quote
    {
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(
            'additional_information',
            [
                'like' => "%\"rvvup_order_id\":\"$rvvupId\"%"
            ]
        );
        $items = $collection->getItems();
        if (count($items) !== 1) {
            return null;
        }
        $quoteId = end($items)->getQuoteId();
        try {
            $quote = $this->cartRepository->get($quoteId);

            if ($storeId && $quote->getStoreId() != (int)$storeId) {
                return null;
            }

            return $quote;
        } catch (NoSuchEntityException $ex) {
            return null;
        }
    }

    /**
     * @param string $field
     * @param string $value
     * @return Quote|null
     */
    public function getOrderByPaymentField(string $field, string $value): ?OrderInterface
    {
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(
            'additional_information',
            [
                'like' => "%\"$field\":\"$value\"%"
            ]
        );
        $collection->clear();
        $items = $collection->getItems();
        if (count($items) !== 1) {
            return null;
        }
        $quoteId = end($items)->getQuoteId();
        try {
            $cart = $this->cartRepository->get($quoteId);
            return $this->order->loadByIncrementId($cart->getReservedOrderId());
        } catch (NoSuchEntityException $ex) {
            return null;
        }
    }

    /**
     * Set checkout method
     *
     * @param Quote $quote
     * @return void
     */
    public function setCheckoutMethod(Quote &$quote): void
    {
        if ($quote->getCustomer() && $quote->getCustomer()->getId()) {
            $quote->setCheckoutMethod(Onepage::METHOD_CUSTOMER);
            return;
        }
        if (!$quote->getCheckoutMethod()) {
            if ($this->checkoutHelper->isAllowedGuestCheckout($quote)) {
                $quote->setCheckoutMethod(Onepage::METHOD_GUEST);
            } else {
                $quote->setCheckoutMethod(Onepage::METHOD_REGISTER);
            }
        }
    }
}
