<?php

declare(strict_types=1);

namespace Rvvup\Payments\Controller\Redirect;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Rvvup\Payments\Api\Data\ValidationInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Service\Capture;
use Rvvup\Payments\Service\Result;
use Rvvup\Payments\Service\VirtualCheckout;

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
     * @var SessionManagerInterface
     */
    private $checkoutSession;

    /** @var ManagerInterface */
    private $messageManager;

    /** @var Capture */
    private $captureService;

    /** @var Result  */
    private $resultService;

    /** @var VirtualCheckout */
    private $virtualCheckoutService;

    /**
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param SessionManagerInterface $checkoutSession
     * @param ManagerInterface $messageManager
     * @param Capture $captureService
     * @param Result $resultService
     * @param VirtualCheckout $virtualCheckoutService
     */
    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        SessionManagerInterface $checkoutSession,
        ManagerInterface $messageManager,
        Capture $captureService,
        Result $resultService,
        VirtualCheckout $virtualCheckoutService
    ) {
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->captureService = $captureService;
        $this->resultService = $resultService;
        $this->virtualCheckoutService = $virtualCheckoutService;
    }

    /**
     * @return ResultInterface|Redirect
     * @throws LocalizedException
     */
    public function execute()
    {
        $rvvupId = (string) $this->request->getParam('rvvup-order-id');
        $paymentStatus = $this->request->getParam('payment-status');
        $quote = $this->captureService->getQuoteByRvvupId($rvvupId);
        $checkoutId = $this->request->getParam('checkout_id');
        $storeId = (string) $this->request->getParam('store_id');
        $origin = 'customer-flow';

        if ($checkoutId && $storeId) {
            $order = $this->captureService->getOrderByPaymentField(Method::MOTO_ID, $checkoutId);

            if (!$rvvupId) {
                $rvvupId = $this->virtualCheckoutService->getRvvupIdByMotoId($checkoutId, $storeId, $order);
            }

            $redirectUrl = $this->virtualCheckoutService->getOrderViewUrl((int)$order->getId());
            return $this->resultService->processOrderResult(
                (string)$order->getId(),
                $rvvupId,
                $origin,
                $storeId,
                false,
                $redirectUrl
            );
        }
        $validate = $this->captureService->validate(
            $quote,
            $rvvupId,
            $paymentStatus,
            $origin
        );

        if (!$validate->getIsValid()) {
            if ($validate->getRestoreQuote()) {
                $this->checkoutSession->restoreQuote();
            }
            if ($validate->getMessage()) {
                $this->messageManager->addErrorMessage($validate['message']);
            }
            if ($validate->getRedirectToCart()) {
                return $this->redirectToCart();
            }
            if ($validate->getRedirectToCheckoutPayment()) {
                return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath(
                    In::FAILURE,
                    ['_secure' => true, '_fragment' => 'payment']
                );
            }
            if ($validate->getAlreadyExists()) {
                if ($quote->getId()) {
                    $this->checkoutSession->setLastSuccessQuoteId($quote->getId());
                    $this->checkoutSession->setLastQuoteId($quote->getId());
                    $this->checkoutSession->setLastOrderId($quote->getReservedOrderId());
                    $this->checkoutSession->setLastRealOrderId($quote->getReservedOrderId());
                    return $this->resultService->processOrderResult(
                        null,
                        $rvvupId,
                        $origin,
                        (string)$quote->getStoreId()
                    );
                }
                return $this->resultService->processOrderResult(
                    (string)$quote->getReservedOrderId(),
                    $rvvupId,
                    $origin,
                    (string)$quote->getStoreId(),
                    true
                );
            }
        }

        $payment = $quote->getPayment();
        $rvvupPaymentId = $payment->getAdditionalInformation(Method::PAYMENT_ID);
        $lastTransactionId = (string)$payment->getAdditionalInformation(Method::TRANSACTION_ID);

        $this->captureService->setCheckoutMethod($quote);
        $validation = $this->captureService->createOrder($rvvupId, $quote, $origin);
        $orderId = $validation->getOrderId();
        $alreadyExists = $validation->getAlreadyExists();

        if ($alreadyExists) {
            $this->checkoutSession->setLastSuccessQuoteId($quote->getId());
            $this->checkoutSession->setLastQuoteId($quote->getId());
            $this->checkoutSession->setLastOrderId($quote->getReservedOrderId());
            $this->checkoutSession->setLastRealOrderId($quote->getReservedOrderId());
            return $this->resultService->processOrderResult(
                (string)$orderId,
                $rvvupId,
                $origin,
                (string)$quote->getStoreId(),
                true
            );
        }

        if (!$orderId) {
            $this->messageManager->addErrorMessage(
                __(
                    'An error occurred while creating your order (ID %1). Please contact us.',
                    $quote->getReservedOrderId() ?: $rvvupId
                )
            );
            $this->checkoutSession->restoreQuote();
            return $this->redirectToCart();
        }

        if (!$this->captureService->paymentCapture(
            $payment,
            $lastTransactionId,
            $rvvupPaymentId,
            $rvvupId,
            $origin,
            $storeId
        )) {
            $this->messageManager->addErrorMessage(
                __(
                    'An error occurred while capturing your order (ID %1). Please contact us.',
                    $rvvupId
                )
            );
            return $this->redirectToCart();
        }

        return $this->resultService->processOrderResult(
            (string)$orderId,
            $rvvupId,
            $origin,
            (string)$quote->getStoreId()
        );
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
}
