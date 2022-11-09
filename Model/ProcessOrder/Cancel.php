<?php

declare(strict_types=1);

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
use Rvvup\Payments\Traits\HasRvvupDataTrait;

class Cancel implements ProcessorInterface
{
    use HasRvvupDataTrait;

    public const TYPE = 'cancel';

    /**
     * @var \Magento\Framework\Event\ManagerInterface|EventManager
     */
    private $eventManager;

    /**
     * @var \Magento\Sales\Api\OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var \Rvvup\Payments\Api\Data\ProcessOrderResultInterfaceFactory
     */
    private $processOrderResultFactory;

    /**
     * Set via di.xml
     *
     * @var \Psr\Log\LoggerInterface|RvvupLog
     */
    private $logger;

    /**
     * List of allowed statuses for Processor.
     *
     * @var array
     */
    private $allowedStatuses = [
        Method::STATUS_CANCELLED,
        Method::STATUS_DECLINED,
        Method::STATUS_EXPIRED
    ];

    /**
     * @param \Magento\Framework\Event\ManagerInterface|EventManager $eventManager
     * @param \Magento\Sales\Api\OrderManagementInterface $orderManagement
     * @param \Rvvup\Payments\Api\Data\ProcessOrderResultInterfaceFactory $processOrderResultFactory
     * @param \Psr\Log\LoggerInterface $logger
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
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param array $rvvupData
     * @return \Rvvup\Payments\Api\Data\ProcessOrderResultInterface
     * @throws \Rvvup\Payments\Exception\PaymentValidationException
     */
    public function execute(OrderInterface $order, array $rvvupData): ProcessOrderResultInterface
    {
        $this->validateIdExists($rvvupData);
        $this->validateStatusAllowed($rvvupData, $this->allowedStatuses);
        $this->validateOrderPayment($order);

        $result = $this->orderManagement->cancel($order->getEntityId());

        if (!$result) {
            $this->logger->debug('Could not cancel order on cancel processor', [
                'order_id' => $order->getEntityId(),
                'order_state' => $order->getState(),
                'order_status' => $order->getStatus()
            ]);
        }

        $this->eventManager->dispatch('rvvup_payments_process_order_cancel_after', [
            'payment_process_type' => self::TYPE,
            'payment_process_result' => $result,
            'event_message' => $result
                ? 'Rvvup Payment has status ' . $rvvupData['status'] . '.'
                : 'Rvvup Payment has status ' . $rvvupData['status'] . ' but failed to cancel the Magento order.',
            'order_id' => $order->getEntityId(),
            'rvvup_id' => $rvvupData['id']
        ]);

        // Set result, regardless if cancellation failed or not.
        /** @var \Rvvup\Payments\Api\Data\ProcessOrderResultInterface $processOrderResult */
        $processOrderResult = $this->processOrderResultFactory->create();
        $processOrderResult->setResultType(ProcessOrderResultInterface::RESULT_TYPE_ERROR);
        $processOrderResult->setRedirectPath(In::FAILURE);
        $processOrderResult->setCustomerMessage('Payment ' . ucfirst(mb_strtolower($rvvupData['status'])));

        return $processOrderResult;
    }

    /**
     * The Order's Payment should be Rvvup
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return void
     * @throws \Rvvup\Payments\Exception\PaymentValidationException
     */
    private function validateOrderPayment(OrderInterface $order): void
    {
        if ($order->getPayment() === null
            || stripos($order->getPayment()->getMethod(), Method::PAYMENT_TITLE_PREFIX) !== 0
        ) {
            throw new PaymentValidationException(__('Order is not paid via Rvvup'));
        }
    }
}
