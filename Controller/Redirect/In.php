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
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterface;
use Rvvup\Payments\Api\Data\SessionMessageInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Payment\PaymentDataGetInterface;
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
     * @param SdkProxy $sdkProxy
     * @param Hash $hash
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
        SdkProxy $sdkProxy,
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
        $payment = $quote->getPayment();
        $lastTransactionId = $payment->getAdditionalInformation('transaction_id');

        $this->validate($rvvupId, $quote, $lastTransactionId);
        $this->setCheckoutMethod($quote);

        $rvvupPaymentId = $payment->getAdditionalInformation(Method::PAYMENT_ID);

        try {
            $this->sdkProxy->paymentCapture($lastTransactionId, $rvvupPaymentId);
            $orderId = $this->quoteManagement->placeOrder($quote->getEntityId(), $payment);
        } catch (\Exception $e) {
            $this->sdkProxy->voidPayment($lastTransactionId, $rvvupPaymentId);
            $this->logger->error(
                'Order placement within rvvup payment failed',
                [
                    'payment_id' => $quote->getPayment()->getEntityId(),
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
            $order = $this->orderRepository->get($orderId);
            // Then get the Rvvup Order by its ID. Rvvup's Redirect In action should always have the correct ID.
            $rvvupData = $this->paymentDataGet->execute($rvvupId);

            if ($rvvupData['status'] != $rvvupData['payments'][0]['status']) {
                $this->processorPool->getProcessor($rvvupData['status'])->execute($order, $rvvupData);
            }

            $result = $this->processorPool->getProcessor($rvvupData['payments'][0]['status'])
                ->execute($order, $rvvupData);

            return $this->processResult($result, $order);
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(
                __(
                    'An error occurred while processing your payment (ID %1). Please contact us.',
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
    private function validate(string $rvvupId, Quote $quote, string $lastTransactionId): ?ResultInterface
    {
        // First validate we have a Rvvup Order ID, silently return to basket page.
        // A standard Rvvup return should always include `rvvup-order-id` param.
        if ($rvvupId === null) {
            $this->logger->error('No Rvvup Order ID provided');

            return $this->redirectToCart();
        }
        $hash = $quote->getPayment()->getAdditionalInformation('quote_hash');
        $quote->collectTotals();
        $savedHash = $this->hashService->getHashForData($quote);
        if ($hash !== $savedHash || $rvvupId !== $lastTransactionId) {
            $this->logger->error(
                'Payment hash or transaction id are invalid during Rvvup Checkout',
                [
                    'payment_id' => $quote->getPayment()->getEntityId(),
                    'quote_id' => $quote->getId(),
                    'last_transaction_id' => $lastTransactionId,
                    'rvvup_order_id' => $rvvupId
                ]
            );
            $this->messageManager->addErrorMessage(
                __(
                    'Your cart was modified after making payment request, please place order again',
                    $rvvupId
                )
            );

            return $this->redirectToCart();
        }
        return null;
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
