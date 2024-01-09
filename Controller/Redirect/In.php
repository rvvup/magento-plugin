<?php

declare(strict_types=1);

namespace Rvvup\Payments\Controller\Redirect;

use Exception;
use Magento\Checkout\Helper\Data;
use Magento\Checkout\Model\Session\Proxy;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Quote\Model\ResourceModel\Quote\Payment\CollectionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterface;
use Rvvup\Payments\Api\Data\SessionMessageInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Payment\PaymentDataGetInterface;
use Rvvup\Payments\Model\ProcessOrder\Cancel;
use Rvvup\Payments\Model\ProcessOrder\ProcessorPool;
use Rvvup\Payments\Model\SdkProxy;
use Rvvup\Payments\Service\Hash;

class In implements HttpGetActionInterface
{
    /**
     * Path constants for different redirects.
     */
    public const SUCCESS = 'checkout/onepage/success';
    public const FAILURE = 'checkout';
    public const ERROR = 'checkout/cart';

    /** @var RequestInterface */
    private $request;

    /** @var ResultFactory */
    private $resultFactory;

    /**
     * Set via di.xml
     * @var SessionManagerInterface|Proxy
     */
    private $checkoutSession;

    /** @var ManagerInterface */
    private $messageManager;

    /** @var PaymentDataGetInterface */
    private $paymentDataGet;

    /** @var ProcessorPool */
    private $processorPool;

    /**
     * Set via di.xml
     *
     * @var LoggerInterface|RvvupLog
     */
    private $logger;

    /** @var QuoteManagement */
    private $quoteManagement;

    /** @var Data */
    private $checkoutHelper;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var Hash */
    private $hashService;

    /** @var SdkProxy */
    private $sdkProxy;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param SessionManagerInterface $checkoutSession
     * @param ManagerInterface $messageManager
     * @param PaymentDataGetInterface $paymentDataGet
     * @param ProcessorPool $processorPool
     * @param LoggerInterface $logger
     * @param QuoteManagement $quoteManagement
     * @param Data $checkoutHelper
     * @param OrderRepositoryInterface $orderRepository
     * @param CollectionFactory $collectionFactory
     * @param SdkProxy $sdkProxy
     * @param CartRepositoryInterface $cartRepository
     * @param Hash $hashService
     */
    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        SessionManagerInterface $checkoutSession,
        ManagerInterface $messageManager,
        PaymentDataGetInterface $paymentDataGet,
        ProcessorPool $processorPool,
        LoggerInterface $logger,
        QuoteManagement $quoteManagement,
        Data $checkoutHelper,
        OrderRepositoryInterface $orderRepository,
        CollectionFactory $collectionFactory,
        SdkProxy $sdkProxy,
        CartRepositoryInterface $cartRepository,
        Hash $hashService
    ) {
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->paymentDataGet = $paymentDataGet;
        $this->processorPool = $processorPool;
        $this->logger = $logger;
        $this->quoteManagement = $quoteManagement;
        $this->checkoutHelper = $checkoutHelper;
        $this->orderRepository = $orderRepository;
        $this->sdkProxy = $sdkProxy;
        $this->collectionFactory = $collectionFactory;
        $this->cartRepository = $cartRepository;
        $this->hashService = $hashService;
    }

    /**
     * @return ResultInterface|Redirect
     * @throws LocalizedException
     */
    public function execute()
    {
        $rvvupId = $this->request->getParam('rvvup-order-id');
        $quote = $this->checkoutSession->getQuote();
        $lastTransactionId = (string)$quote->getPayment()->getAdditionalInformation('transaction_id');
        $error = $this->validate($rvvupId, $quote, $lastTransactionId);

        if ($error) {
            return $error;
        }
        $payment = $quote->getPayment();

        $this->setCheckoutMethod($quote);

        $rvvupPaymentId = $payment->getAdditionalInformation(Method::PAYMENT_ID);

        try {
            $orderId = $this->quoteManagement->placeOrder($quote->getEntityId(), $payment);
        } catch (\Exception $e) {
            $this->logger->error(
                'Order placement within rvvup payment failed',
                [
                    'payment_id' => $payment->getEntityId(),
                    'last_transaction_id' => $lastTransactionId,
                    'rvvup_order_id' => $rvvupId,
                    'message' => $e->getMessage()
                ]
            );
            $this->messageManager->addErrorMessage(
                __(
                    'An error occurred while creating your order (ID %1). Please contact us.',
                    $rvvupId
                )
            );
            return $this->redirectToCart();
        }
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
            $this->messageManager->addErrorMessage(
                __(
                    'An error occurred while capturing your order (ID %1). Please contact us.',
                    $rvvupId
                )
            );
            return $this->redirectToCart();
        }

        try {
            $order = $this->orderRepository->get($orderId);
            // Then get the Rvvup Order by its ID. Rvvup's Redirect In action should always have the correct ID.
            $rvvupData = $this->paymentDataGet->execute($rvvupId);

            if ($rvvupData['status'] != $rvvupData['payments'][0]['status']) {
                if ($rvvupData['payments'][0]['status'] !== Method::STATUS_AUTHORIZED) {
                    $this->processorPool->getProcessor($rvvupData['status'])->execute($order, $rvvupData);
                }
            }

            $result = $this->processorPool->getProcessor($rvvupData['payments'][0]['status'])
                ->execute($order, $rvvupData);

            if (get_class($this->processorPool->getProcessor($rvvupData['payments'][0]['status'])) == Cancel::class) {
                $this->checkoutSession->restoreQuote();
            }

            return $this->processResult($result, $order);
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(
                __(
                    'An error occurred while processing your payment (ID %1). Please contact us. ',
                    $rvvupId
                )
            );

            $this->logger->error('Error while processing Rvvup Order status with message: ' . $e->getMessage(), [
                'rvvup_order_id' => $rvvupId,
                'rvvup_order_status' => $rvvupData['payments'][0]['status'] ?? ''
            ]);

            return $this->redirectToCart();
        }
    }

    /**
     * Set checkout method
     *
     * @param Quote $quote
     * @return void
     */
    private function setCheckoutMethod(Quote $quote): void
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
     * @return ResultInterface
     */
    private function redirectToCart(): ResultInterface
    {
        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath(
            self::ERROR,
            ['_secure' => true]
        );
    }

    /**
     * @param string $rvvupId
     * @param Quote $quote
     * @param string $lastTransactionId
     * @return Redirect|ResultInterface|void
     */
    private function validate(string $rvvupId, Quote &$quote, string &$lastTransactionId): ?ResultInterface
    {
        // First validate we have a Rvvup Order ID, silently return to basket page.
        // A standard Rvvup return should always include `rvvup-order-id` param.
        if ($rvvupId === null) {
            $this->logger->error('No Rvvup Order ID provided');
            $this->checkoutSession->restoreQuote();
            return $this->redirectToCart();
        }

        if (!$quote->getItems()) {
            $quote = $this->getQuoteByRvvupId($rvvupId);
            $lastTransactionId = (string)$quote->getPayment()->getAdditionalInformation('transaction_id');
        }
        if (empty($quote->getId())) {
            $this->logger->error('Missing quote for Rvvup payment', [$rvvupId, $lastTransactionId]);
            $this->messageManager->addErrorMessage(
                __(
                    'An error occurred while processing your payment (ID %1). Please contact us. ',
                    $rvvupId
                )
            );
            return $this->redirectToCart();
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
            $this->messageManager->addErrorMessage(
                __(
                    'Your cart was modified after making payment request, please place order again. ' . $rvvupId
                )
            );
            return $this->redirectToCart();
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
            $this->messageManager->addErrorMessage(
                __(
                    'Your payment was placed before making this payment request, please place order again. ' . $rvvupId
                )
            );
            return $this->redirectToCart();
        }
        return null;
    }

    /**
     * @param string $rvvupId
     * @return null
     * @throws NoSuchEntityException
     */
    private function getQuoteByRvvupId(string $rvvupId): ?Quote
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
        if (sizeof($items) > 1) {
            return null;
        }
        $quoteId = end($items)->getQuoteId();
        return $this->cartRepository->get($quoteId);
    }

    /**
     * @param ProcessOrderResultInterface $result
     * @param OrderInterface $order
     * @return Redirect
     */
    private function processResult(ProcessOrderResultInterface $result, OrderInterface $order): Redirect
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $params = ['_secure' => true];

        // If specifically we are redirecting the user to the checkout page,
        // set the redirect to the payment step
        // and set the messages to be added to the custom group.
        if ($result->getRedirectPath() === self::FAILURE) {
            $params['_fragment'] = 'payment';
            $messageGroup = SessionMessageInterface::MESSAGE_GROUP;
        }

        $result->setSessionMessage($messageGroup ?? null);

        $redirect->setPath($result->getRedirectPath(), $params);

        return $redirect;
    }
}
