<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\ProcessOrder;

use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterfaceFactory;
use Rvvup\Payments\Controller\Redirect\In;
use Rvvup\Payments\Exception\PaymentValidationException;
use Rvvup\Payments\Gateway\Method;

class Declined implements ProcessorInterface
{
    public const TYPE = 'declined';

    /** @var EventManager */
    private $eventManager;
    /** @var OrderManagementInterface  */
    private $orderManagement;
    /** @var ProcessOrderResultInterfaceFactory */
    private $processOrderResultFactory;
    /** @var LoggerInterface|RvvupLog  */
    private $logger;

    /**
     * @param EventManager $eventManager
     * @param OrderManagementInterface $orderManagement
     * @param ProcessOrderResultInterfaceFactory $processOrderResultFactory
     * @param LoggerInterface $logger
     * @return void
     */
    public function __construct(
        EventManager $eventManager,
        OrderManagementInterface $orderManagement,
        ProcessOrderResultInterfaceFactory $processOrderResultFactory,
        LoggerInterface $logger
    ) {
        $this->eventManager = $eventManager;
        $this->orderManagement = $orderManagement;
        $this->processOrderResultFactory = $processOrderResultFactory;
        $this->logger = $logger;
    }

    /**
     * @param OrderInterface $order
     * @param array $rvvupData
     * @return ProcessOrderResultInterface
     * @throws PaymentValidationException
     */
    public function execute(OrderInterface $order, array $rvvupData): ProcessOrderResultInterface
    {
        if ($order->getPayment() === null
            || stripos($order->getPayment()->getMethod(), Method::PAYMENT_TITLE_PREFIX) !== 0
        ) {
            throw new PaymentValidationException(__('Order is not paid via Rvvup'));
        }

        $result = $this->orderManagement->cancel($order->getEntityId());

        if (!$result) {
            $this->logger->debug('Could not cancel order on declined processor', ['order_id' => $order->getEntityId()]);
        }

        $this->eventManager->dispatch('rvvup_payments_process_order_declined_after', [
            'payment_process_type' => self::TYPE,
            'payment_process_result' => $result,
            'event_message' => $result
                ? 'Rvvup Payment was declined.'
                : 'Rvvup Payment was declined but failed to cancel the Magento order.',
            'order_id' => $order->getEntityId(),
            'rvvup_id' => $rvvupData['id'] ?? null
        ]);

        // Set result, regardless if cancellation failed or not.
        /** @var \Rvvup\Payments\Api\Data\ProcessOrderResultInterface $processOrderResult */
        $processOrderResult = $this->processOrderResultFactory->create();
        $processOrderResult->setResultType(ProcessOrderResultInterface::RESULT_TYPE_ERROR);
        $processOrderResult->setRedirectUrl(In::FAILURE);
        $processOrderResult->setCustomerMessage('Payment Declined');

        return $processOrderResult;
    }
}
