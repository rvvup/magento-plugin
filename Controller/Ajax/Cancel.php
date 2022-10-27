<?php declare(strict_types=1);

namespace Rvvup\Payments\Controller\Ajax;

use Exception;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterface;
use Rvvup\Payments\Controller\Redirect\In;
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
    /** @var PaymentDataGetInterface  */
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

    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();

        try {
            $rvvupData = $this->paymentDataGet->execute(
                $order->getPayment() !== null && $order->getPayment()->getCcTransId() !== null
                    ? $order->getPayment()->getCcTransId()
                    : ''
            );

            $result = $this->processorPool->getProcessor($rvvupData['status'] ?? '')->execute($order, $rvvupData);

            // Restore quote if the result would be of type error.
            if ($result->getResultType() === ProcessOrderResultInterface::RESULT_TYPE_ERROR) {
                $this->checkoutSession->restoreQuote();
            }

            $this->messageManager->getMessages(true);
            $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            // Add no cache headers in the AJAX response as we don't return a layout.
            // @see https://developer.adobe.com/commerce/php/development/cache/page/public-content/#non-cacheable-page-checklist
            $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
            $response->setData([
                'success' => true,
                'message' => $result->getCustomerMessage() ?? __('Order cancelled')->render()
            ]);
            return $response;
        } catch (Exception $e) {
            /** @var \Magento\Framework\Controller\Result\Redirect $redirect */
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

            $this->messageManager->addErrorMessage(__('An error occurred while processing your payment.'));
            $this->logger->error($e->getMessage());
            $redirect->setPath(In::FAILURE, ['_secure' => true]);

            return $redirect;
        }
    }
}
