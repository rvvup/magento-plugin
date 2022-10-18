<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\ProcessOrder;

use Exception;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceOrderInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterfaceFactory;
use Rvvup\Payments\Controller\Redirect\In;
use Rvvup\Payments\Exception\PaymentValidationException;
use Rvvup\Payments\Gateway\Method;

class Complete implements ProcessorInterface
{
    public const TYPE = 'complete';

    /** @var EventManager */
    private $eventManager;
    /** @var InvoiceOrderInterface */
    private $invoiceOrder;
    /** @var ProcessOrderResultInterfaceFactory */
    private $processOrderResultFactory;
    /** @var LoggerInterface|RvvupLog */
    private $logger;

    /**
     * @param EventManager $eventManager
     * @param InvoiceOrderInterface $invoiceOrder
     * @param ProcessOrderResultInterfaceFactory $processOrderResultFactory
     * @param LoggerInterface $logger
     * @return void
     */
    public function __construct(
        EventManager $eventManager,
        InvoiceOrderInterface $invoiceOrder,
        ProcessOrderResultInterfaceFactory $processOrderResultFactory,
        LoggerInterface $logger
    ) {
        $this->eventManager = $eventManager;
        $this->invoiceOrder = $invoiceOrder;
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

        /** @var \Rvvup\Payments\Api\Data\ProcessOrderResultInterface $processOrderResult */
        $processOrderResult = $this->processOrderResultFactory->create();

        try {
            $invoiceId = $this->invoiceOrder->execute($order->getEntityId(), true);

            $this->eventManager->dispatch('rvvup_payments_process_order_complete_after', [
                'payment_process_type' => self::TYPE,
                'payment_process_result' => true,
                'order_id' => $order->getEntityId(),
                'rvvup_data' => $rvvupData,
                'invoice_id' => $invoiceId
            ]);

            $processOrderResult->setResultType(ProcessOrderResultInterface::RESULT_TYPE_SUCCESS);
            $processOrderResult->setRedirectUrl(In::SUCCESS);
        } catch (Exception $e) {
            $this->logger->error('Error during processing order complete on SUCCESS status: ' . $e->getMessage(), [
                'order_id' => $order->getEntityId()
            ]);

            $processOrderResult->setResultType(ProcessOrderResultInterface::RESULT_TYPE_ERROR);
            $processOrderResult->setCustomerMessage(
                'An error occurred while processing your payment. Please contact us.'
            );
            $processOrderResult->setRedirectUrl(In::FAILURE);
        }

        return $processOrderResult;
    }
}
