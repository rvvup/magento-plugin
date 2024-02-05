<?php

declare(strict_types=1);

namespace Rvvup\Payments\Service;

use Magento\Checkout\Helper\Data;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\OrderIncrementIdChecker;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterface;
use Rvvup\Payments\Api\Data\SessionMessageInterface;
use Rvvup\Payments\Controller\Redirect\In;
use Rvvup\Payments\Exception\PaymentValidationException;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Payment\PaymentDataGetInterface;
use Rvvup\Payments\Model\ProcessOrder\Cancel;
use Rvvup\Payments\Model\ProcessOrder\ProcessorPool;
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

    /** @var PaymentDataGetInterface */
    private $paymentDataGet;

    /** @var ProcessorPool */
    private $processorPool;

    /** @var Hash */
    private $hashService;

    /** @var CollectionFactory */
    private $collectionFactory;

    /** @var CartRepositoryInterface */
    private $cartRepository;

    /** @var ResultFactory */
    private $resultFactory;

    /**
     * Set via di.xml
     * @var SessionManagerInterface
     */
    private $checkoutSession;

    /** @var Data */
    private $checkoutHelper;

    /** @var OrderInterface */
    private $order;

    /** @var OrderIncrementIdChecker */
    private $orderIncrementChecker;

    /**
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param QuoteResource $quoteResource
     * @param QuoteManagement $quoteManagement
     * @param SdkProxy $sdkProxy
     * @param PaymentDataGetInterface $paymentDataGet
     * @param ProcessorPool $processorPool
     * @param Hash $hashService
     * @param CollectionFactory $collectionFactory
     * @param CartRepositoryInterface $cartRepository
     * @param ResultFactory $resultFactory
     * @param SessionManagerInterface $checkoutSession
     * @param Data $checkoutHelper
     * @param OrderInterface $order
     * @param OrderIncrementIdChecker $orderIncrementIdChecker
     * @param LoggerInterface $logger
     */
    public function __construct(
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        OrderRepositoryInterface $orderRepository,
        QuoteResource $quoteResource,
        QuoteManagement $quoteManagement,
        SdkProxy $sdkProxy,
        PaymentDataGetInterface $paymentDataGet,
        ProcessorPool $processorPool,
        Hash $hashService,
        CollectionFactory $collectionFactory,
        CartRepositoryInterface $cartRepository,
        ResultFactory $resultFactory,
        SessionManagerInterface $checkoutSession,
        Data $checkoutHelper,
        OrderInterface $order,
        OrderIncrementIdChecker $orderIncrementIdChecker,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->orderRepository = $orderRepository;
        $this->quoteResource = $quoteResource;
        $this->quoteManagement = $quoteManagement;
        $this->sdkProxy = $sdkProxy;
        $this->paymentDataGet = $paymentDataGet;
        $this->processorPool = $processorPool;
        $this->collectionFactory = $collectionFactory;
        $this->cartRepository = $cartRepository;
        $this->resultFactory = $resultFactory;
        $this->checkoutSession = $checkoutSession;
        $this->checkoutHelper = $checkoutHelper;
        $this->order = $order;
        $this->orderIncrementChecker = $orderIncrementIdChecker;
        $this->hashService = $hashService;
    }

    /**
     * @param string $rvvupOrderId
     * @return OrderInterface
     * @throws \Exception
     */
    public function getOrderByRvvupId(string $rvvupOrderId): OrderInterface
    {
        // Saerch for the payment record by the Rvvup order ID which is stored in the credit card field.
        $searchCriteria = $this->searchCriteriaBuilder->addFilter(
            'additional_information',
            '%' . $rvvupOrderId . '%',
            'like'
        )->create();

        $resultSet = $this->orderPaymentRepository->getList($searchCriteria);

        // We always expect 1 payment object for a Rvvup Order ID.
        if ($resultSet->getTotalCount() !== 1) {
            $this->logger->warning('Webhook error. Payment not found for order.', [
                'rvvup_order_id' => $rvvupOrderId,
                'payments_count' => $resultSet->getTotalCount()
            ]);
            throw new PaymentValidationException(__('Error finding order with rvvup_id ' . $rvvupOrderId));
        }

        $payments = $resultSet->getItems();
        /** @var \Magento\Sales\Api\Data\OrderPaymentInterface $payment */
        $payment = reset($payments);
        return $this->orderRepository->get($payment->getParentId());
    }

    /**
     * @param string $rvvupId
     * @param Quote $quote
     * @param string $lastTransactionId
     * @return array
     */
    public function validate(string $rvvupId, Quote &$quote, string &$lastTransactionId): array
    {
        // First validate we have a Rvvup Order ID, silently return to basket page.
        // A standard Rvvup return should always include `rvvup-order-id` param.
        if ($rvvupId === null) {
            $this->logger->error('No Rvvup Order ID provided');
            return [
                'is_valid' => false,
                'redirect_to_cart' => true,
                'restore_quote' => true,
                'message' => '',
                'already_exists' => false
            ];
        }

        if (!$quote->getIsActive()) {
            return [
                'is_valid' => false,
                'redirect_to_cart' => false,
                'restore_quote' => false,
                'message' => '',
                'already_exists' => true
            ];
        }

        if (!$quote->getItems()) {
            $quote = $this->getQuoteByRvvupId($rvvupId);
            $lastTransactionId = (string)$quote->getPayment()->getAdditionalInformation('transaction_id');
        }
        if (empty($quote->getId())) {
            $this->logger->error('Missing quote for Rvvup payment', [$rvvupId, $lastTransactionId]);
            $message = __(
                'An error occurred while processing your payment (ID %1). Please contact us. ',
                $rvvupId
            );
            return [
                'is_valid' => false,
                'redirect_to_cart' => true,
                'restore_quote' => false,
                'message' => $message,
                'already_exists' => false
            ];
        }

        $hash = $quote->getPayment()->getAdditionalInformation('quote_hash');
        $quote->collectTotals();
        $savedHash = $this->hashService->getHashForData($quote);
        if ($hash !== $savedHash) {
            $this->logger->error(
                'Payment hash is invalid during Rvvup Checkout',
                [
                    'payment_id' => $quote->getPayment()->getEntityId(),
                    'quote_id' => $quote->getId(),
                    'last_transaction_id' => $lastTransactionId,
                    'rvvup_order_id' => $rvvupId
                ]
            );

            $message = __(
                'Your cart was modified after making payment request, please place order again. ' . $rvvupId
            );
            return [
                'is_valid' => false,
                'redirect_to_cart' => true,
                'restore_quote' => false,
                'message' => $message,
                'already_exists' => false
            ];
        }
        if ($rvvupId !== $lastTransactionId) {
            $this->logger->error(
                'Payment transaction id is invalid during Rvvup Checkout',
                [
                    'payment_id' => $quote->getPayment()->getEntityId(),
                    'quote_id' => $quote->getId(),
                    'last_transaction_id' => $lastTransactionId,
                    'rvvup_order_id' => $rvvupId
                ]
            );
            $message = __(
                'This checkout cannot complete, a new cart was opened in another tab. ' . $rvvupId
            );
            return [
                'is_valid' => false,
                'redirect_to_cart' => true,
                'restore_quote' => false,
                'message' => $message,
                'already_exists' => false
            ];
        }

        if ($quote->getReservedOrderId()) {
            if ($this->orderIncrementChecker->isIncrementIdUsed($quote->getReservedOrderId())) {
                return [
                    'is_valid' => false,
                    'redirect_to_cart' => false,
                    'restore_quote' => false,
                    'message' => '',
                    'already_exists' => true
                ];
            }
        }

        return [
            'is_valid' => true,
            'redirect_to_cart' => false,
            'restore_quote' => false,
            'message' => '',
            'already_exists' => false
        ];
    }

    /**
     * @param string $rvvupId
     * @param Quote $quote
     * @return array
     */
    public function createOrder(string $rvvupId, Quote $quote): array
    {
        $this->quoteResource->beginTransaction();
        $lastTransactionId = (string)$quote->getPayment()->getAdditionalInformation('transaction_id');
        $payment = $quote->getPayment();

        try {
            if ($this->orderIncrementChecker->isIncrementIdUsed($quote->getReservedOrderId())) {
                return $quote->getReservedOrderId();
            }

            $orderId = $this->quoteManagement->placeOrder($quote->getEntityId(), $payment);
            $this->quoteResource->commit();
            return ['id' => $orderId, 'reserved' => false];
        } catch (NoSuchEntityException $e) {
            return ['id' => $quote->getReservedOrderId(), 'reserved' => true];
        } catch (\Exception $e) {
            $this->quoteResource->rollback();
            if (str_contains($e->getMessage(), AdapterInterface::ERROR_ROLLBACK_INCOMPLETE_MESSAGE)) {
                return ['id' => $quote->getReservedOrderId(), 'reserved' => true];
            }
            $this->logger->error(
                'Order placement within rvvup payment failed',
                [
                    'payment_id' => $payment->getEntityId(),
                    'last_transaction_id' => $lastTransactionId,
                    'rvvup_order_id' => $rvvupId,
                    'message' => $e->getMessage()
                ]
            );
            return ['id' => false, 'reserved' => false];
        }
    }

    /**
     * @param Payment $payment
     * @param string $lastTransactionId
     * @param string $rvvupPaymentId
     * @param string $rvvupId
     * @return bool
     */
    public function paymentCapture(
        Quote\Payment $payment,
        string $lastTransactionId,
        string $rvvupPaymentId,
        string $rvvupId
    ): bool {
        try {
            if ($payment->getMethodInstance()->getCaptureType() !== 'MANUAL') {
                $this->sdkProxy->paymentCapture($lastTransactionId, $rvvupPaymentId);
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'Order placement failed during payment capture',
                [
                    'payment_id' => $payment->getEntityId(),
                    'last_transaction_id' => $lastTransactionId,
                    'rvvup_order_id' => $rvvupId,
                    'message' => $e->getMessage()
                ]
            );
            return false;
        }
        return true;
    }

    /**
     * Update Magento Order based on Rvuup Order and payment statuses
     * @param string|null $orderId
     * @param string $rvvupId
     * @param bool $reservedOrderId
     * @return Redirect
     */
    public function processOrderResult(?string $orderId, string $rvvupId, bool $reservedOrderId = false): Redirect
    {
        if (!$orderId) {
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath(
                In::SUCCESS,
                ['_secure' => true]
            );
        }

        try {
            if ($reservedOrderId) {
                $order = $this->order->loadByIncrementId($orderId);
            } else {
                $order = $this->orderRepository->get($orderId);
            }
            // Then get the Rvvup Order by its ID. Rvvup's Redirect In action should always have the correct ID.
            $rvvupData = $this->paymentDataGet->execute($rvvupId);

            if ($rvvupData['status'] != $rvvupData['payments'][0]['status']) {
                if ($rvvupData['payments'][0]['status'] !== Method::STATUS_AUTHORIZED) {
                    $this->processorPool->getProcessor($rvvupData['status'])->execute($order, $rvvupData);
                }
            }

            $processor = $this->processorPool->getProcessor($rvvupData['payments'][0]['status']);
            $result = $processor->execute($order, $rvvupData);
            if (get_class($processor) == Cancel::class) {
                return $this->processResultPage($result, true);
            }
            return $this->processResultPage($result, false);
        } catch (\Exception $e) {
            $this->logger->error('Error while processing Rvvup Order status with message: ' . $e->getMessage(), [
                'rvvup_order_id' => $rvvupId,
                'rvvup_order_status' => $rvvupData['payments'][0]['status'] ?? ''
            ]);

            if (isset($order)) {
                $order->addStatusToHistory(
                    $order->getStatus(),
                    'Failed to update Magento order from Rvvup order status check',
                    false
                );
                $this->orderRepository->save($order);
            }

            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath(
                In::SUCCESS,
                ['_secure' => true]
            );
        }
    }

    /**
     * @param string $rvvupId
     * @return Quote|null
     */
    public function getQuoteByRvvupId(string $rvvupId): ?Quote
    {
        /** @var \Magento\Quote\Model\ResourceModel\Quote\Payment\Collection $collection */
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
            return $this->cartRepository->get($quoteId);
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

    /**
     * @param ProcessOrderResultInterface $result
     * @param bool $restoreQuote
     * @return Redirect
     */
    private function processResultPage(ProcessOrderResultInterface $result, bool $restoreQuote): Redirect
    {
        if ($restoreQuote) {
            $this->checkoutSession->restoreQuote();
        }

        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $params = ['_secure' => true];

        // If specifically we are redirecting the user to the checkout page,
        // set the redirect to the payment step
        // and set the messages to be added to the custom group.
        if ($result->getRedirectPath() === IN::FAILURE) {
            $params['_fragment'] = 'payment';
            $messageGroup = SessionMessageInterface::MESSAGE_GROUP;
        }

        $result->setSessionMessage($messageGroup ?? null);

        $redirect->setPath($result->getRedirectPath(), $params);

        return $redirect;
    }
}
