<?php declare(strict_types=1);

namespace Rvvup\Payments\Controller\Ajax;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Psr\Log\LoggerInterface;
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
            $this->processorPool->getProcessor('CANCELLED')
                ->execute($order, [], $redirect);
            $this->messageManager->getMessages(true);
            $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            $response->setData(['success' => true, 'message' => 'Order canceled']);
            return $response;
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while processing your payment.'));
            $this->logger->error($e->getMessage());
            $redirect->setPath('checkout/cart', ['_secure' => true]);
        }

        return $redirect;
    }
}
