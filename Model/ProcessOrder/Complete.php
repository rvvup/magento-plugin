<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\ProcessOrder;

use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceOrderInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\Payment\Rvvup;
use Magento\Framework\Controller\Result\Redirect;

class Complete implements ProcessorInterface
{
    /** @var InvoiceService */
    private $invoiceService;
    /** @var ManagerInterface */
    private $messageManager;
    /** @var LoggerInterface */
    private $logger;
    /** @var InvoiceOrderInterface */
    private $invoiceOrder;
    /** @var BuilderInterface */
    private $transactionBuilder;
    /** @var OrderRepositoryInterface */
    private $orderRepository;
    /** @var InvoiceRepositoryInterface */
    private $invoiceRepository;

    /**
     * @param InvoiceService $invoiceService
     * @param ManagerInterface $messageManager
     * @param LoggerInterface $logger
     * @param InvoiceOrderInterface $invoiceOrder
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param BuilderInterface $builder
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        InvoiceService $invoiceService,
        ManagerInterface $messageManager,
        LoggerInterface $logger,
        InvoiceOrderInterface $invoiceOrder,
        InvoiceRepositoryInterface $invoiceRepository,
        BuilderInterface $builder,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->invoiceService = $invoiceService;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
        $this->invoiceOrder = $invoiceOrder;
        $this->transactionBuilder = $builder;
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
    }

    /**
     * @param OrderInterface $order
     * @param array $rvvupData
     * @param Redirect $redirect
     * @return void
     */
    public function execute(OrderInterface $order, array $rvvupData, Redirect $redirect): void
    {
        try {
            $payment = $order->getPayment();
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->pay();
            $transactionBuilder = $this->transactionBuilder->setPayment($payment);
            $transactionBuilder->setOrder($order);
            $transactionBuilder->setFailSafe(true);
            $transactionBuilder->setTransactionId($payment->getLastTransId());
            $transactionBuilder->setAdditionalInformation($payment->getTransactionAdditionalInfo());
            $transactionBuilder->setSalesDocument($invoice);
            $transactionBuilder->build(Transaction::TYPE_CAPTURE);
            /** \Magento\Sales\Model\Order $order */
            $order->addStatusToHistory('processing', 'Payment processed successfully', true);
            $this->orderRepository->save($order);
            $this->invoiceRepository->save($invoice);
            $redirect->setPath('checkout/onepage/success', ['_secure' => true]);
        } catch (\Exception $e) {
            $this->logger->error('Error during order place on SUCCESS status: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(
                __('An error occurred while processing your payment. Please contact us.')
            );
            $redirect->setPath('checkout/cart', ['_secure' => true]);
        }
    }
}
