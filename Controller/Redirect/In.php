<?php declare(strict_types=1);

namespace Rvvup\Payments\Controller\Redirect;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\Payment\Rvvup;
use Rvvup\Payments\Model\ProcessOrder\ProcessorPool;
use Rvvup\Payments\Model\ProcessOrder\Complete;
use Rvvup\Payments\Model\SdkProxy;

class In implements HttpGetActionInterface
{
    /** @var RequestInterface */
    private $request;
    /** @var ResultFactory */
    private $resultFactory;
    /** @var SdkProxy */
    private $sdkProxy;

    /**
     * Set via di.xml
     *
     * @var SessionManagerInterface|\Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /** @var ManagerInterface */
    private $messageManager;

    /**
     * Set via di.xml
     *
     * @var LoggerInterface|RvvupLog
     */
    private $logger;
    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;
    /** @var OrderRepositoryInterface */
    private $orderRepository;
    /** @var ProcessorPool */
    private $processorPool;

    public const SUCCESS = 'checkout/onepage/success';
    public const FAILURE = 'checkout/cart';

    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        SdkProxy $sdkProxy,
        SessionManagerInterface $checkoutSession,
        ManagerInterface $messageManager,
        LoggerInterface $logger,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderRepositoryInterface $orderRepository,
        ProcessorPool $processorPool
    ) {
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->sdkProxy = $sdkProxy;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderRepository = $orderRepository;
        $this->processorPool = $processorPool;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Redirect
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        $rvvupId = $this->request->getParam('rvvup-order-id');
        $order = $this->checkoutSession->getLastRealOrder();

        $rvvupData = $this->sdkProxy->getOrder($rvvupId);
        $lastTransactionId = $order->getPayment()->getLastTransId();
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        if ($rvvupId !== $lastTransactionId) {
            $this->logger->error(
                'Payment ID when redirecting from Rvvup checkout does not match order in checkout session'
            );
            $this->messageManager->addErrorMessage(__(
                'An error occurred while processing your payment (ID %1)',
                $rvvupId
            ));
            return $redirect->setPath('checkout/cart', ['_secure' => true]);
        }

        /** @var \Magento\Framework\Controller\Result\Redirect $redirect */

        try {
            $this->processorPool->getProcessor($rvvupData['status'])
                ->execute($order, $rvvupData, $redirect);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while processing your payment.'));
            $this->logger->error($e->getMessage());
            $redirect->setPath('checkout/cart', ['_secure' => true]);
        }
        return $redirect;
    }
}
