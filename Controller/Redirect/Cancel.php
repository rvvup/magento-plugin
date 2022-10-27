<?php declare(strict_types=1);

namespace Rvvup\Payments\Controller\Redirect;

use Exception;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterface;
use Rvvup\Payments\Model\Payment\PaymentDataGetInterface;
use Rvvup\Payments\Model\ProcessOrder\ProcessorPool;

class Cancel implements HttpGetActionInterface
{
    /** @var ResultFactory */
    private $resultFactory;
    /** @var SessionManagerInterface */
    private $checkoutSession;
    /** @var ManagerInterface */
    private $messageManager;
    /** @var PaymentDataGetInterface */
    private $paymentDataGet;
    /** @var ProcessorPool */
    private $processorPool;
    /** @var LoggerInterface */
    private $logger;

    /**
     * @param ResultFactory $resultFactory
     * @param SessionManagerInterface $checkoutSession
     * @param ManagerInterface $messageManager
     * @param PaymentDataGetInterface $paymentDataGet
     * @param ProcessorPool $processorPool
     * @param LoggerInterface $logger
     * @return void
     */
    public function __construct(
        ResultFactory $resultFactory,
        SessionManagerInterface $checkoutSession,
        ManagerInterface $messageManager,
        PaymentDataGetInterface $paymentDataGet,
        ProcessorPool $processorPool,
        LoggerInterface $logger
    ) {
        $this->resultFactory = $resultFactory;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->paymentDataGet = $paymentDataGet;
        $this->processorPool = $processorPool;
        $this->logger = $logger;
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface|\Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();

        /** @var \Magento\Framework\Controller\Result\Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        try {
            $rvvupData = $this->paymentDataGet->execute(
                $order->getPayment() !== null && $order->getPayment()->getCcTransId() !== null
                    ? $order->getPayment()->getCcTransId()
                    : ''
            );

            $result = $this->processorPool->getProcessor($rvvupData['status'] ?? '')->execute($order, $rvvupData);

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
            $redirect->setPath(In::FAILURE, ['_secure' => true]);
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
                $this->messageManager->addSuccessMessage(__($processOrderResult->getCustomerMessage()));
                break;
            case ProcessOrderResultInterface::RESULT_TYPE_ERROR:
                $this->messageManager->addErrorMessage(__($processOrderResult->getCustomerMessage()));
                break;
            default:
                $this->messageManager->addWarningMessage(__($processOrderResult->getCustomerMessage()));
        }
    }
}
