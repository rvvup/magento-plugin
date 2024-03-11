<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\ProcessOrder;

use Exception;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceOrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Magento\Sales\Model\Order\OrderStateResolverInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterfaceFactory;
use Rvvup\Payments\Controller\Redirect\In;
use Rvvup\Payments\Exception\PaymentValidationException;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\RvvupConfigProvider;

class Complete implements ProcessorInterface
{
    public const TYPE = 'complete';

    /**
     * @var \Magento\Framework\Event\ManagerInterface|EventManager
     */
    private $eventManager;

    /**
     * @var \Magento\Sales\Api\InvoiceOrderInterface
     */
    private $invoiceOrder;

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

    /** @var OrderConfig */
    private $config;
    
    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /**
     * @param ManagerInterface|EventManager $eventManager
     * @param InvoiceOrderInterface $invoiceOrder
     * @param ProcessOrderResultInterfaceFactory $processOrderResultFactory
     * @param OrderConfig $config
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        EventManager $eventManager,
        InvoiceOrderInterface $invoiceOrder,
        ProcessOrderResultInterfaceFactory $processOrderResultFactory,
        OrderConfig $config,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->eventManager = $eventManager;
        $this->invoiceOrder = $invoiceOrder;
        $this->processOrderResultFactory = $processOrderResultFactory;
        $this->config = $config;
        $this->orderRepository = $orderRepository;
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
            || strpos($order->getPayment()->getMethod(), Method::PAYMENT_TITLE_PREFIX) !== 0
        ) {
            if (strpos($order->getPayment()->getMethod(), RvvupConfigProvider::CODE) !== 0) {
                throw new PaymentValidationException(__('Order is not paid via Rvvup'));
            }
        }

        /** @var ProcessOrderResultInterface $processOrderResult */
        $processOrderResult = $this->processOrderResultFactory->create();

        try {
            // If order is already successfully processed & invoiced (either webhook or redirect IN, whichever first),
            // no-action & return success result.
            if ($this->isProcessed($order)) {
                $processOrderResult->setResultType(ProcessOrderResultInterface::RESULT_TYPE_SUCCESS);
                $processOrderResult->setRedirectPath(In::SUCCESS);

                return $processOrderResult;
            }

            // Don't notify the customer, this will be done on the event,
            // as we need to trigger the order confirmation email first & then the invoice if enabled.
            $invoiceId = $this->invoiceOrder->execute($order->getEntityId(), true);

            /** Manually set to processing */
            $order->setState(Processing::TYPE);
            $order->setStatus($this->config->getStateDefaultStatus($order->getState()));
            $this->orderRepository->save($order);

            $this->eventManager->dispatch('rvvup_payments_process_order_complete_after', [
                'payment_process_type' => self::TYPE,
                'payment_process_result' => true,
                'order_id' => $order->getEntityId(),
                'rvvup_data' => $rvvupData,
                'invoice_id' => $invoiceId
            ]);

            $processOrderResult->setResultType(ProcessOrderResultInterface::RESULT_TYPE_SUCCESS);
            $processOrderResult->setRedirectPath(In::SUCCESS);
        } catch (CommandException $e) {
            $processOrderResult->setResultType(ProcessOrderResultInterface::RESULT_TYPE_ERROR);
            $processOrderResult->setRedirectPath(In::FAILURE);
            $processOrderResult->setCustomerMessage($e->getMessage());
        } catch (Exception $e) {
            $this->logger->error('Error during processing order complete on SUCCESS status: ' . $e->getMessage(), [
                'order_id' => $order->getEntityId()
            ]);

            $processOrderResult->setResultType(ProcessOrderResultInterface::RESULT_TYPE_ERROR);
            $processOrderResult->setCustomerMessage(
                'An error occurred while processing your payment. Please contact us.'
            );
            $processOrderResult->setRedirectPath(In::FAILURE);
        }

        return $processOrderResult;
    }

    /**
     * Check whether an order has already been processed.
     * Criteria are
     * 1 - Order total is paid
     * 2 - Order total is invoiced
     * 3 - Order state is not New & Order state is not Pending Payment (Default states for unpaid Rvvup orders)
     *
     * @param OrderInterface $order
     * @return bool
     */
    private function isProcessed(OrderInterface $order): bool
    {
        return $order->getGrandTotal() === $order->getTotalPaid()
            && $order->getGrandTotal() === $order->getTotalInvoiced()
            && ($order->getState() !== Order::STATE_NEW && $order->getState() !== Order::STATE_PENDING_PAYMENT);
    }
}
