<?php declare(strict_types=1);

namespace Rvvup\Payments\Controller\Redirect;

use Exception;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterface;
use Rvvup\Payments\Api\Data\SessionMessageInterface;
use Rvvup\Payments\Model\Payment\PaymentDataGetInterface;
use Rvvup\Payments\Model\ProcessOrder\ProcessorPool;

/**
 * ToDo: Evaluate if this is still being used by Rvvup & delete if not as it is not used internally in the module.
 */
class Cancel implements HttpGetActionInterface
{
    /** @var ResultFactory */
    private $resultFactory;

    /**
     * Set via etc/frontend/di.xml
     *
     * @var \Magento\Framework\Session\SessionManagerInterface|\Magento\Checkout\Model\Session\Proxy
     */
    private $checkoutSession;

    /** @var ManagerInterface */
    private $messageManager;

    /** @var PaymentDataGetInterface */
    private $paymentDataGet;

    /** @var ProcessorPool */
    private $processorPool;

    /**
     * Set via etc/frontend/di.xml
     *
     * @var \Psr\Log\LoggerInterface|RvvupLog
     */
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

        try {
            /** @var \Magento\Framework\Controller\Result\Redirect $redirect */
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

            $params = ['_secure' => true];

            $rvvupData = $this->paymentDataGet->execute(
                $order->getPayment() !== null && $order->getPayment()->getCcTransId() !== null
                    ? $order->getPayment()->getCcTransId()
                    : ''
            );

            $result = $this->processorPool->getProcessor($rvvupData['payments'][0]['status'] ?? '')
                ->execute($order, $rvvupData);

            // Restore quote if the result would be of type error.
            if ($result->getResultType() === ProcessOrderResultInterface::RESULT_TYPE_ERROR) {
                $this->checkoutSession->restoreQuote();
            }

            // If specifically we are redirecting the user to the checkout page,
            // set the redirect to the payment step
            // and set the messages to be added to the custom group.
            if ($result->getRedirectPath() === In::FAILURE) {
                $params['_fragment'] = 'payment';
                $messageGroup = SessionMessageInterface::MESSAGE_GROUP;
            }

            $result->setSessionMessage($messageGroup ?? null);
            $redirect->setPath($result->getRedirectPath(), $params);
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while processing your payment.'));
            $this->logger->error($e->getMessage());
            $redirect->setPath(In::ERROR, ['_secure' => true]);
        }
        return $redirect;
    }
}
