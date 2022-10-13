<?php declare(strict_types=1);

namespace Rvvup\Payments\Controller\Redirect;

use Exception;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\ProcessOrder\ProcessorPool;
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
        ProcessorPool $processorPool
    ) {
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->sdkProxy = $sdkProxy;
        $this->checkoutSession = $checkoutSession;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
        $this->processorPool = $processorPool;
    }

    /**
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $rvvupId = $this->request->getParam('rvvup-order-id');

        // First validate we have a Rvvup Order ID, silently return to basket page.
        // A standard Rvvup return should always include `rvvup-order-id` param.
        if ($rvvupId === null) {
            $this->logger->error('No Rvvup Order ID provided');

            return $redirect->setPath(self::FAILURE, ['_secure' => true]);
        }

        // Get Last success order of the checkout session and validate it exists and that it has a payment.
        $order = $this->checkoutSession->getLastRealOrder();

        if ($order === null || $order->getPayment() === null) {
            $this->logger->error(
                'Could not find ' . ($order === null ? 'order' : 'payment') . ' for the checkout session',
                [
                    'order_id' => $order !== null ? $order->getEntityId() : ''
                ]
            );
            $this->messageManager->addErrorMessage(__(
                'An error occurred while processing your payment (ID %1)',
                $rvvupId
            ));

            return $redirect->setPath(self::FAILURE, ['_secure' => true]);
        }

        // Now validate that last payment transaction ID matches the Rvvup ID.
        $lastTransactionId = $order->getPayment()->getLastTransId();

        if ($rvvupId !== $lastTransactionId) {
            $this->logger->error(
                'Payment ID when redirecting from Rvvup checkout does not match order in checkout session',
                [
                    'order_id' => $order->getEntityId(),
                    'payment_id' => $order->getPayment()->getEntityId(),
                    'last_transaction_id' => $lastTransactionId,
                    'rvvup_order_id' => $rvvupId
                ]
            );
            $this->messageManager->addErrorMessage(__(
                'An error occurred while processing your payment (ID %1)',
                $rvvupId
            ));

            return $redirect->setPath(self::FAILURE, ['_secure' => true]);
        }

        // Then get the Rvvup Order by its ID. Rvvup's redirect In action should always have the correct ID.
        try {
            $rvvupData = $this->sdkProxy->getOrder($rvvupId);
        } catch (Exception $ex) {
            $this->logger->error('Error while fetching Rvvup Order with message: ' . $ex->getMessage(), [
                'rvvup_order_id' => $rvvupId
            ]);

            $this->messageManager->addErrorMessage(__(
                'An error occurred while processing your payment (ID %1)',
                $rvvupId
            ));

            return $redirect->setPath(self::FAILURE, ['_secure' => true]);
        }

        if (!isset($rvvupData['status'])) {
            $this->logger->error('Rvvup Order does not have a status returned from the API', [
                'rvvup_order_id' => $rvvupId
            ]);

            $this->messageManager->addErrorMessage(__(
                'An error occurred while processing your payment (ID %1)',
                $rvvupId
            ));

            return $redirect->setPath(self::FAILURE, ['_secure' => true]);
        }

        try {
            $this->processorPool->getProcessor($rvvupData['status'])->execute($order, $rvvupData, $redirect);
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__('An error occurred while processing your payment.'));
            $this->logger->error('Error while processing Rvvup Order status with message: ' . $e->getMessage(), [
                'order_id' => $order->getEntityId(),
                'rvvup_order_id' => $rvvupId,
                'rvvup_order_status' => $rvvupData['status']
            ]);
            $redirect->setPath(self::FAILURE, ['_secure' => true]);
        }

        return $redirect;
    }
}
