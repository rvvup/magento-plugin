<?php

declare(strict_types=1);

namespace Rvvup\Payments\Controller\CardPayments;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Sales\Api\Data\OrderInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\SdkProxy;
use Rvvup\Payments\Service\Order;
use Psr\Log\LoggerInterface;

class Cancel implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /** @var ResultFactory */
    private $resultFactory;

    /** @var SdkProxy */
    private $sdkProxy;

    /** @var Validator */
    private $formKeyValidator;

    /** @var Session */
    private $session;

    /** @var Order */
    private $orderService;

    /**
     * @param ResultFactory $resultFactory
     * @param SdkProxy $sdkProxy
     * @param Validator $formKeyValidator
     * @param Session $session
     * @param Order $orderService
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResultFactory $resultFactory,
        SdkProxy $sdkProxy,
        Validator $formKeyValidator,
        Session $session,
        Order $orderService,
        LoggerInterface $logger
    ) {
        $this->resultFactory = $resultFactory;
        $this->sdkProxy = $sdkProxy;
        $this->formKeyValidator = $formKeyValidator;
        $this->session = $session;
        $this->orderService = $orderService;
        $this->logger = $logger;
    }

    public function execute()
    {
        $quote = $this->session->getQuote();

        try {
            if ($quote->getId()) {
                $orders = $this->orderService->getAllOrdersByQuote($quote);
                /** @var OrderInterface $order */
                foreach ($orders as $order) {
                    $payment = $order->getPayment();
                    if ($payment->getMethod()) {
                        if (strpos($payment->getMethod(), Method::PAYMENT_TITLE_PREFIX) === 0) {
                            if ($order->canCancel()) {
                                $order->cancel();
                            }
                            $rvvupOrderId = $payment->getAdditionalInformation('rvvup_order_id');
                            $order = $this->sdkProxy->getOrder($rvvupOrderId);
                            if ($order && isset($order['payments'])) {
                                $paymentId = $order['payments'][0]['id'];
                                $this->sdkProxy->cancelPayment($paymentId, (string) $rvvupOrderId);
                            }
                        }

                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning('Rvvup order cancellation failed with message : ' . $e->getMessage());
        }

        $response = $this->resultFactory->create($this->resultFactory::TYPE_JSON);
        $response->setHttpResponseCode(200);
        return $response;
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
