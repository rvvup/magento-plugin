<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\ProcessOrder;

use Exception;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceOrderInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Controller\Result\Redirect;

class Complete implements ProcessorInterface
{
    /** @var ManagerInterface */
    private $messageManager;
    /** @var InvoiceOrderInterface */
    private $invoiceOrder;
    /** @var LoggerInterface */
    private $logger;

    /**
     * @param ManagerInterface $messageManager
     * @param InvoiceOrderInterface $invoiceOrder
     * @param LoggerInterface $logger
     * @return void
     */
    public function __construct(
        ManagerInterface $messageManager,
        InvoiceOrderInterface $invoiceOrder,
        LoggerInterface $logger
    ) {
        $this->messageManager = $messageManager;
        $this->invoiceOrder = $invoiceOrder;
        $this->logger = $logger;
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
            $this->invoiceOrder->execute($order->getEntityId(), true);
            $redirect->setPath('checkout/onepage/success', ['_secure' => true]);
        } catch (Exception $e) {
            $this->logger->error('Error during order place on SUCCESS status: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(
                __('An error occurred while processing your payment. Please contact us.')
            );
            $redirect->setPath('checkout/cart', ['_secure' => true]);
        }
    }
}
