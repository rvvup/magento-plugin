<?php declare(strict_types=1);

namespace Rvvup\Payments\Controller\Redirect;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\ProcessOrder\ProcessorPool;
use Rvvup\Payments\Model\SdkProxy;

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
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while processing your payment.'));
            $this->logger->error($e->getMessage());
            $redirect->setPath('checkout/cart', ['_secure' => true]);
        }
        return $redirect;
    }
}
