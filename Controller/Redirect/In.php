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
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Service\Capture;

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

    /**
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param SessionManagerInterface $checkoutSession
     * @param ManagerInterface $messageManager
     * @param Capture $captureService
     */
    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        SessionManagerInterface $checkoutSession,
        ManagerInterface $messageManager,
        Capture $captureService
    ) {
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->captureService = $captureService;
    }

    /**
     * @return ResultInterface|Redirect
     * @throws LocalizedException
     */
    public function execute()
    {
        $rvvupId = $this->request->getParam('rvvup-order-id');
        $paymentStatus = $this->request->getParam('payment-status');

        if ($paymentStatus == Method::STATUS_CANCELLED ||
            $paymentStatus == Method::STATUS_EXPIRED ||
            $paymentStatus == Method::STATUS_DECLINED ||
            $paymentStatus == Method::STATUS_AUTHORIZATION_EXPIRED) {
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath(
                self::FAILURE,
                ['_secure' => true, '_fragment' => 'payment']
            );
        }

        $quote = $this->checkoutSession->getQuote();

        if (!$quote->getId()) {
            $quote = $this->captureService->getQuoteByRvvupId($rvvupId);
        }

        $payment = $quote->getPayment();
        $rvvupPaymentId = $payment->getAdditionalInformation(Method::PAYMENT_ID);
        $lastTransactionId = (string)$payment->getAdditionalInformation(Method::TRANSACTION_ID);

        $validate = $this->captureService->validate($rvvupId, $quote, $lastTransactionId);

        if (!$validate['is_valid']) {
            if ($validate['restore_quote']) {
                $this->checkoutSession->restoreQuote();
            }
            if ($validate['message']) {
                $this->messageManager->addErrorMessage($validate['message']);
            }
            if ($validate['redirect_to_cart']) {
                return $this->redirectToCart();
            }
            if ($validate['already_exists']) {
                if ($quote->getId()) {
                    $this->checkoutSession->setLastSuccessQuoteId($quote->getId());
                    $this->checkoutSession->setLastQuoteId($quote->getId());
                    $this->checkoutSession->setLastOrderId($quote->getReservedOrderId());
                    $this->checkoutSession->setLastRealOrderId($quote->getReservedOrderId());
                    return $this->captureService->processOrderResult(null, $rvvupId);
                }
                return $this->captureService->processOrderResult((string)$quote->getReservedOrderId(), $rvvupId, true);
            }
        }

        $this->captureService->setCheckoutMethod($quote);
        $order = $this->captureService->createOrder($rvvupId, $quote);
        $orderId = $order['id'];
        $reserved = $order['reserved'];

        if ($reserved) {
            $this->checkoutSession->setLastSuccessQuoteId($quote->getId());
            $this->checkoutSession->setLastQuoteId($quote->getId());
            $this->checkoutSession->setLastOrderId($quote->getReservedOrderId());
            $this->checkoutSession->setLastRealOrderId($quote->getReservedOrderId());
            return $this->captureService->processOrderResult((string)$orderId, $rvvupId, true);
        }

        if (!$orderId) {
            $this->messageManager->addErrorMessage(
                __(
                    'An error occurred while creating your order (ID %1). Please contact us.',
                    $rvvupId
                )
            );
            $this->checkoutSession->restoreQuote();
            return $this->redirectToCart();
        }

        if (!$this->captureService->paymentCapture($payment, $lastTransactionId, $rvvupPaymentId, $rvvupId)) {
            $this->messageManager->addErrorMessage(
                __(
                    'An error occurred while capturing your order (ID %1). Please contact us.',
                    $rvvupId
                )
            );
            return $this->redirectToCart();
        }

        return $this->captureService->processOrderResult((string)$orderId, $rvvupId);
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
