<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\ProcessOrder;

use Exception;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterfaceFactory;
use Rvvup\Payments\Controller\Redirect\In;
use Rvvup\Payments\Exception\PaymentValidationException;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Logger;
use Rvvup\Payments\Model\RvvupConfigProvider;

class Processing implements ProcessorInterface
{
    public const TYPE = 'processing';

    /** @var EventManager */
    private $eventManager;
    /** @var OrderRepositoryInterface */
    private $orderRepository;
    /** @var ProcessOrderResultInterfaceFactory */
    private $processOrderResultFactory;

    /** @var LoggerInterface|Logger */
    private $logger;

    /** @var DateTime  */
    private $dateTime;

    /**
     * @param EventManager $eventManager
     * @param OrderRepositoryInterface $orderRepository
     * @param ProcessOrderResultInterfaceFactory $processOrderResultFactory
     * @param LoggerInterface $logger
     * @param DateTime $dateTime
     */
    public function __construct(
        EventManager $eventManager,
        OrderRepositoryInterface $orderRepository,
        ProcessOrderResultInterfaceFactory $processOrderResultFactory,
        LoggerInterface $logger,
        DateTime $dateTime
    ) {
        $this->eventManager = $eventManager;
        $this->orderRepository = $orderRepository;
        $this->processOrderResultFactory = $processOrderResultFactory;
        $this->logger = $logger;
        $this->dateTime = $dateTime;
    }

    /**
     * Change state & state of order to `Payment Review`.
     *
     * @param OrderInterface $order
     * @param array $rvvupData
     * @param string $origin
     * @return ProcessOrderResultInterface
     * @throws PaymentValidationException
     */
    public function execute(OrderInterface $order, array $rvvupData, string $origin): ProcessOrderResultInterface
    {
        /** @var \Rvvup\Payments\Api\Data\ProcessOrderResultInterface $processOrderResult */
        $processOrderResult = $this->processOrderResultFactory->create();
        if ($order->getPayment() === null) {
            throw new PaymentValidationException(__('Missing payment for order.'));
        }
        if (strpos($order->getPayment()->getMethod(), RvvupConfigProvider::CODE) !== 0) {
            throw new PaymentValidationException(__('Order is not paid via Rvvup'));
        }

        try {
            $originalOrderState = $order->getState();
            $originalOrderStatus = $order->getStatus();

            $order = $this->changeNewOrderStatus($order);

            $eventMessage = 'Rvvup Payment is being processed.';

            $payment = $rvvupData['payments'][0];
            if ($this->inAuthorizedManualPayment($payment)) {
                $formattedExpirationDate = $this->dateTime->date(
                    "jS M Y H:i:s T",
                    strtotime($payment["authorizationExpiresAt"])
                );

                $eventMessage = "Payment authorization expires at <strong>" . $formattedExpirationDate . "</strong>. " .
                    "<br />" .
                    "When the authorization expires, the order will be cancelled " .
                    "and the funds will be returned to the customer. <br />" .
                    "Please capture the funds manually using the Rvvup Dashboard/by invoicing the order in magento." .
                    "<br />";
            }

            $this->eventManager->dispatch('rvvup_payments_process_order_processing_after', [
                'payment_process_type' => self::TYPE,
                'payment_process_result' => true,
                'event_message' => $eventMessage,
                'order_id' => $order->getEntityId(),
                'rvvup_id' => $rvvupData['id'] ?? null,
                'original_order_state' => $originalOrderState,
                'original_order_status' => $originalOrderStatus
            ]);

            // If we don't set result type, the warning message will be used.
            $processOrderResult->setCustomerMessage(
                'Your payment is being processed and is pending confirmation. ' .
                'You will receive an email confirmation when the payment is confirmed.'
            );
            $processOrderResult->setRedirectPath(In::SUCCESS);
        } catch (Exception $e) {
            $this->logger->addRvvupError(
                'Error during order processing on ' . $rvvupData['payments'][0]['status']
                . ' status: ',
                $e->getMessage(),
                $rvvupData['id'],
                $rvvupData['payments'][0]['id'],
                $order->getId(),
                $origin
            );

            $processOrderResult->setResultType(ProcessOrderResultInterface::RESULT_TYPE_ERROR);
            $processOrderResult->setCustomerMessage(
                'An error occurred while processing your payment. Please contact us.'
            );
            $processOrderResult->setRedirectPath(In::FAILURE);
        }

        return $processOrderResult;
    }

    /**
     * If a payment is till being processed, move the order to pending payment state.
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    private function changeNewOrderStatus(OrderInterface $order): OrderInterface
    {
        // We only change state & status for New Orders
        if ($order->getState() !== Order::STATE_NEW || $order->getStatus() !== 'pending') {
            return $order;
        }

        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus(Order::STATE_PENDING_PAYMENT);

        return $this->orderRepository->save($order);
    }

    private function inAuthorizedManualPayment(array $payment): bool
    {
        return isset($payment["authorizationExpiresAt"]) &&
            $payment["captureType"] === "MANUAL" &&
            $payment['status'] == Method::STATUS_AUTHORIZED;
    }
}
