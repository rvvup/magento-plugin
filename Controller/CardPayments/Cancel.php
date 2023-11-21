<?php

declare(strict_types=1);

namespace Rvvup\Payments\Controller\CardPayments;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Service\Order;
use Psr\Log\LoggerInterface;

class Cancel implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /** @var ResultFactory */
    private $resultFactory;

    /** @var Validator */
    private $formKeyValidator;

    /** @var Session */
    private $session;

    /** @var Order */
    private $orderService;

    /** @var LoggerInterface */
    private $logger;

    /** @var OrderRepositoryInterface  */
    private $orderRepository;

    /**
     * @param ResultFactory $resultFactory
     * @param Validator $formKeyValidator
     * @param Session $session
     * @param Order $orderService
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResultFactory $resultFactory,
        Validator $formKeyValidator,
        Session $session,
        Order $orderService,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->resultFactory = $resultFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->session = $session;
        $this->orderService = $orderService;
        $this->orderRepository = $orderRepository;
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
                $orders = $this->orderService->getAllOrdersByQuote($quote);
                /** @var OrderInterface $order */
                foreach ($orders as $order) {
                    $this->cancelRvvupOrder($order);
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
     * @param OrderInterface $order
     * @return void
     */
    private function cancelRvvupOrder(OrderInterface $order): void
    {
        $payment = $order->getPayment();
        if ($payment->getMethod()) {
            if (strpos($payment->getMethod(), Method::PAYMENT_TITLE_PREFIX) === 0) {
                if ($order->canCancel()) {
                    $order->cancel();
                    $this->orderRepository->save($order);
                }
            }
        }
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
