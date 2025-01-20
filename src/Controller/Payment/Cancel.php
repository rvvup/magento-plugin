<?php

declare(strict_types=1);

namespace Rvvup\Payments\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\SdkProxy;

class Cancel implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /** @var ResultFactory */
    private $resultFactory;

    /** @var Validator */
    private $formKeyValidator;

    /** @var Session */
    private $session;

    /** @var LoggerInterface */
    private $logger;

    /** @var OrderRepositoryInterface  */
    private $orderRepository;

    /** @var SdkProxy  */
    private $sdkProxy;

    /**
     * @param ResultFactory $resultFactory
     * @param Validator $formKeyValidator
     * @param Session $session
     * @param OrderRepositoryInterface $orderRepository
     * @param SdkProxy $sdkProxy
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResultFactory $resultFactory,
        Validator $formKeyValidator,
        Session $session,
        OrderRepositoryInterface $orderRepository,
        SdkProxy $sdkProxy,
        LoggerInterface $logger
    ) {
        $this->resultFactory = $resultFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->session = $session;
        $this->orderRepository = $orderRepository;
        $this->sdkProxy = $sdkProxy;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function execute(): ResultInterface
    {
        $quote = $this->session->getQuote();

        try {
            if ($quote->getId()) {
                $this->cancelRvvupOrder($quote->getPayment());
            }
        } catch (\Exception $e) {
            $this->logger->warning('Rvvup order cancellation failed with message : ' . $e->getMessage());
        }

        $response = $this->resultFactory->create($this->resultFactory::TYPE_JSON);
        $response->setHttpResponseCode(200);
        return $response;
    }

    /**
     * @param PaymentInterface $payment
     * @return void
     */
    private function cancelRvvupOrder(PaymentInterface $payment): void
    {
        $rvvupOrderId = $payment->getAdditionalInformation(Method::ORDER_ID);
        $paymentId = $payment->getAdditionalInformation(Method::PAYMENT_ID);
        $this->sdkProxy->cancelPayment($paymentId, $rvvupOrderId);
    }

    /**
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        try {
            return $this->formKeyValidator->validate($request);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }
}
