<?php declare(strict_types=1);

namespace Rvvup\Payments\Controller\Redirect;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterface;
use Rvvup\Payments\Api\Data\SessionMessageInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Payment\PaymentDataGetInterface;
use Rvvup\Payments\Model\ProcessOrder\ProcessorPool;
use Rvvup\Payments\Service\Order;

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
     * @var \Magento\Framework\Session\SessionManagerInterface|\Magento\Checkout\Model\Session\Proxy
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
     * @var \Psr\Log\LoggerInterface|RvvupLog
     */
    private $logger;

    /** @var Order  */
    private $orderService;

    /**
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param SessionManagerInterface $checkoutSession
     * @param ManagerInterface $messageManager
     * @param PaymentDataGetInterface $paymentDataGet
     * @param ProcessorPool $processorPool
     * @param LoggerInterface $logger
     * @param Order $orderService
     */
    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        SessionManagerInterface $checkoutSession,
        ManagerInterface $messageManager,
        PaymentDataGetInterface $paymentDataGet,
        ProcessorPool $processorPool,
        LoggerInterface $logger,
        Order $orderService
    ) {
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->paymentDataGet = $paymentDataGet;
        $this->processorPool = $processorPool;
        $this->logger = $logger;
        $this->orderService = $orderService;
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $rvvupId = $this->request->getParam('rvvup-order-id');

        // First validate we have a Rvvup Order ID, silently return to basket page.
        // A standard Rvvup return should always include `rvvup-order-id` param.
        if ($rvvupId === null) {
            $this->logger->error('No Rvvup Order ID provided');

            return $this->redirectToCart();
        }

        // Get Last success order of the checkout session and validate it exists and that it has a payment.
        $order = $this->checkoutSession->getLastRealOrder();

        /** Fix for card payments, as they don't have order in session */
        if (empty($order->getData())) {
            $quote = $this->checkoutSession->getQuote();
            if (!empty($quote->getEntityId())) {
                $orders = $this->orderService->getAllOrdersByQuote($quote);
                $order = end($orders);
                $this->checkoutSession->setData('last_real_order_id', $order->getIncrementId());
            }
        }

        if (!$order->getEntityId() || $order->getPayment() === null) {
            $this->logger->error(
                'Could not find ' . (!$order->getEntityId() ? 'order' : 'payment') . ' for the checkout session',
                [
                    'order_id' => $order->getEntityId()
                ]
            );
            $this->messageManager->addErrorMessage(
                __(
                    'An error occurred while processing your payment (ID %1). Please contact us.',
                    $rvvupId
                )
            );

            return $this->redirectToCart();
        }

        // Now validate that last payment transaction ID matches the Rvvup ID.
        $lastTransactionId = $order->getPayment()->getLastTransId();

        if ($rvvupId !== $lastTransactionId) {
            $this->logger->error(
                'Payment ID when redirecting from Rvvup checkout does not match order in checkout session',
                [
                    'order_id' => $order->getEntityId(),
                    'payment_id' => $order->getPayment()->getEntityId(),
                    'last_transaction_id' => $lastTransactionId,
                    'rvvup_order_id' => $rvvupId
                ]
            );
            $this->messageManager->addErrorMessage(
                __(
                    'An error occurred while processing your payment (ID %1). Please contact us.',
                    $rvvupId
                )
            );

            return $this->redirectToCart();
        }

        try {
            // Then get the Rvvup Order by its ID. Rvvup's Redirect In action should always have the correct ID.
            $rvvupData = $this->paymentDataGet->execute($rvvupId);

            if (empty($rvvupData)) {
                $this->checkoutSession->restoreQuote();
                return $this->redirectToCart();
            }

            if ($rvvupData['status'] != $rvvupData['payments'][0]['status']) {
                if ($rvvupData['payments'][0]['status'] !== Method::STATUS_AUTHORIZED) {
                    $this->processorPool->getProcessor($rvvupData['status'])->execute($order, $rvvupData);
                }
            }

            $result = $this->processorPool->getProcessor($rvvupData['payments'][0]['status'])
                ->execute($order, $rvvupData);

            /** @var \Magento\Framework\Controller\Result\Redirect $redirect */
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

            $params = ['_secure' => true];

            // Restore quote if the result would be of type error.
            if ($result->getResultType() === ProcessOrderResultInterface::RESULT_TYPE_ERROR
                || $result->getRedirectPath() == self::ERROR) {
                $this->checkoutSession->restoreQuote();
            }

            // If specifically we are redirecting the user to the checkout page,
            // set the redirect to the payment step
            // and set the messages to be added to the custom group.
            if ($result->getRedirectPath() === self::FAILURE) {
                $params['_fragment'] = 'payment';
                $messageGroup = SessionMessageInterface::MESSAGE_GROUP;
            }

            $this->setSessionMessage($result, $messageGroup ?? null);

            $redirect->setPath($result->getRedirectPath(), $params);

            return $redirect;
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(
                __(
                    'An error occurred while processing your payment (ID %1). Please contact us.',
                    $rvvupId
                )
            );

            $this->logger->error('Error while processing Rvvup Order status with message: ' . $e->getMessage(), [
                'order_id' => $order->getEntityId(),
                'rvvup_order_id' => $rvvupId,
                'rvvup_order_status' => $rvvupData['payments'][0]['status'] ?? ''
            ]);

            return $this->redirectToCart();
        }
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface|\Magento\Framework\Controller\Result\Redirect
     */
    private function redirectToCart()
    {
        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath(
            self::ERROR,
            ['_secure' => true]
        );
    }

    /**
     * Set the session message in the message container.
     *
     * Only handle success & error messages.
     * Default to Warning container if none of the above
     * Allow custom message group for the checkout page specifically.
     *
     * @param \Rvvup\Payments\Api\Data\ProcessOrderResultInterface $processOrderResult
     * @param string|null $messageGroup
     * @return void
     */
    private function setSessionMessage(
        ProcessOrderResultInterface $processOrderResult,
        ?string $messageGroup = null
    ): void {
        // If no message to display, no action.
        if ($processOrderResult->getCustomerMessage() === null) {
            return;
        }

        switch ($processOrderResult->getResultType()) {
            case ProcessOrderResultInterface::RESULT_TYPE_SUCCESS:
                $this->messageManager->addSuccessMessage(__($processOrderResult->getCustomerMessage()), $messageGroup);
                break;
            case ProcessOrderResultInterface::RESULT_TYPE_ERROR:
                $this->messageManager->addErrorMessage(__($processOrderResult->getCustomerMessage()), $messageGroup);
                break;
            default:
                $this->messageManager->addWarningMessage(__($processOrderResult->getCustomerMessage()), $messageGroup);
        }
    }
}
