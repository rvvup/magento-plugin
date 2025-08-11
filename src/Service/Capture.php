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
use Magento\Quote\Model\ResourceModel\Quote\Payment\CollectionFactory;
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

        $paymentCount = $resultSet->getTotalCount();
        // We always expect 1 payment object for a Rvvup Order ID.
        if ($paymentCount !== 1) {
            $this->logger->addRvvupError(
                'Payment count is ' . $paymentCount . ' for order.',
                null,
                $rvvupOrderId,
                null,
                null,
                null
            );
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
        $payment = $quote->getPayment();

        try {
            $reservedId = $quote->getReservedOrderId();
            if ($this->orderIncrementChecker->isIncrementIdUsed($reservedId)) {
                $this->logger->addRvvupError(
                    'Increment ID is already used ' . $reservedId,
                    null,
                    $rvvupId,
                    null,
                    $reservedId ?? null,
                    $origin
                );
                return $this->validationInterfaceFactory->create(
                    [
                        'data' => [
                            ValidationInterface::ORDER_ID => $reservedId,
                            ValidationInterface::ALREADY_EXISTS => true]
                    ]
                );
            }

            $orderId = $this->quoteManagement->placeOrder($quote->getEntityId(), $payment);
            return $this->validationInterfaceFactory->create(
                [
                    'data' => [
                        ValidationInterface::ORDER_ID => $orderId,
                        ValidationInterface::ALREADY_EXISTS => false
                    ]
                ]
            );
        } catch (NoSuchEntityException $e) {
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
                    'data' =>
                        [
                            ValidationInterface::ORDER_ID => $quote->getReservedOrderId(),
                            ValidationInterface::ALREADY_EXISTS => true
                        ]
                ]
            );
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), AdapterInterface::ERROR_ROLLBACK_INCOMPLETE_MESSAGE) !== false) {
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
     * @param string $rvvupOrderId
     * @param string $rvvupPaymentId
     * @param string $origin
     * @param string $storeId
     * @return string|null
     */
    public function paymentCapture(
        string $rvvupOrderId,
        string $rvvupPaymentId,
        string $origin,
        string $storeId
    ): ?string {
        try {
            return $this->sdkProxy->paymentCapture($rvvupOrderId, $rvvupPaymentId, $storeId);
        } catch (\Exception $e) {
            $this->logger->addRvvupError(
                'Rvvup order capture failed during payment capture',
                $e->getMessage(),
                $rvvupOrderId,
                $rvvupPaymentId,
                null,
                $origin
            );
            return null;
        }
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
