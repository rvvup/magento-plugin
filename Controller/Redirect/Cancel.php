<?php declare(strict_types=1);

namespace Rvvup\Payments\Controller\Redirect;

use Exception;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterface;
use Rvvup\Payments\Model\ProcessOrder\ProcessorPool;

class Cancel implements HttpGetActionInterface
{
    /** @var ResultFactory */
    private $resultFactory;
    /** @var SessionManagerInterface */
    private $checkoutSession;
    /** @var ManagerInterface */
    private $messageManager;
    /** @var LoggerInterface */
    private $logger;
    /** @var ProcessorPool */
    private $processorPool;

    /**
     * @param ResultFactory $resultFactory
     * @param SessionManagerInterface $checkoutSession
     * @param ManagerInterface $messageManager
     * @param LoggerInterface $logger
     * @param ProcessorPool $processorPool
     */
    public function __construct(
        ResultFactory $resultFactory,
        SessionManagerInterface $checkoutSession,
        ManagerInterface $messageManager,
        LoggerInterface $logger,
        ProcessorPool $processorPool
    ) {
        $this->resultFactory = $resultFactory;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
        $this->processorPool = $processorPool;
    }

    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();

        /** @var \Magento\Framework\Controller\Result\Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        try {
            $result = $this->processorPool->getProcessor('CANCELLED')->execute($order, []);

            // Set the result message if any to the session.
            $this->setSessionMessage($result);

            // Restore quote if the result would be of type error.
            if ($result->getResultType() === ProcessOrderResultInterface::RESULT_TYPE_ERROR) {
                $this->checkoutSession->restoreQuote();
            }

            $redirect->setPath($result->getRedirectUrl(), ['_secure' => true]);
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while processing your payment.'));
            $this->logger->error($e->getMessage());
            $redirect->setPath('checkout/cart', ['_secure' => true]);
        }
        return $redirect;
    }

    /**
     * Set the session message in the message container.
     *
     * Only handle success & error messages.
     * Default to Warning container if none of the above.
     *
     * @param \Rvvup\Payments\Api\Data\ProcessOrderResultInterface $processOrderResult
     * @return void
     */
    private function setSessionMessage(ProcessOrderResultInterface $processOrderResult): void
    {
        // If no message to display, no action.
        if ($processOrderResult->getCustomerMessage() === null) {
            return;
        }

        switch ($processOrderResult->getResultType()) {
            case ProcessOrderResultInterface::RESULT_TYPE_SUCCESS:
                $this->messageManager->addSuccessMessage(__('%1', $processOrderResult->getCustomerMessage()));
                break;
            case ProcessOrderResultInterface::RESULT_TYPE_ERROR:
                $this->messageManager->addErrorMessage(__('%1', $processOrderResult->getCustomerMessage()));
                break;
            default:
                $this->messageManager->addWarningMessage(__('%1', $processOrderResult->getCustomerMessage()));
        }
    }
}
