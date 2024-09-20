<?php
declare(strict_types=1);

namespace Rvvup\Payments\Service;

use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\Payment;
use Magento\Store\Model\App\Emulation;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\Data\ProcessOrderResultInterface;
use Rvvup\Payments\Api\Data\SessionMessageInterface;
use Rvvup\Payments\Controller\Redirect\In;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Payment\PaymentDataGetInterface;
use Rvvup\Payments\Model\ProcessOrder\Cancel;
use Rvvup\Payments\Model\ProcessOrder\ProcessorPool;

class Result
{
    /** @var ResultFactory */
    private $resultFactory;

    /**
     * Set via di.xml
     * @var SessionManagerInterface
     */
    private $checkoutSession;

    /** Set via di.xml
     * @var LoggerInterface
     */
    private $logger;

    /** @var OrderRepositoryInterface  */
    private $orderRepository;

    /** @var ProcessorPool */
    private $processorPool;

    /** @var PaymentDataGetInterface */
    private $paymentDataGet;

    /** @var OrderInterface */
    private $order;

    /** @var Emulation */
    private $emulation;

    /** @var Payment */
    private $paymentResource;

    /**
     * @param ResultFactory $resultFactory
     * @param SessionManagerInterface $checkoutSession
     * @param OrderRepositoryInterface $orderRepository
     * @param ProcessorPool $processorPool
     * @param PaymentDataGetInterface $paymentDataGet
     * @param OrderInterface $order
     * @param Emulation $emulation
     * @param LoggerInterface $logger
     * @param Payment $paymentResource
     */
    public function __construct(
        ResultFactory $resultFactory,
        SessionManagerInterface $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        ProcessorPool $processorPool,
        PaymentDataGetInterface $paymentDataGet,
        OrderInterface $order,
        Emulation $emulation,
        LoggerInterface $logger,
        Payment $paymentResource
    ) {
        $this->resultFactory = $resultFactory;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->processorPool = $processorPool;
        $this->paymentDataGet = $paymentDataGet;
        $this->order = $order;
        $this->emulation = $emulation;
        $this->logger = $logger;
        $this->paymentResource = $paymentResource;
    }

    /**
     * @param string|null $orderId
     * @param string $rvvupId
     * @param string $origin
     * @param int $storeId
     * @param bool $reservedOrderId
     * @param string|null $redirectUrl
     * @return Redirect
     * Update Magento Order based on Rvuup Order and payment statuses
     */
    public function processOrderResult(
        ?string $orderId,
        string $rvvupId,
        string $origin,
        int $storeId,
        bool $reservedOrderId = false,
        string $redirectUrl = null
    ): Redirect {
        $this->emulation->startEnvironmentEmulation($storeId);

        if (!$orderId) {
            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath(
                In::SUCCESS,
                ['_secure' => true]
            );
        }

        try {
            if ($reservedOrderId) {
                $order = $this->order->loadByIncrementId($orderId);
            } else {
                $order = $this->orderRepository->get($orderId);
            }

            // Then get the Rvvup Order by its ID. Rvvup's Redirect In action should always have the correct ID.
            $rvvupData = $this->paymentDataGet->execute($rvvupId);

            if ($rvvupData['status'] != $rvvupData['payments'][0]['status']) {
                if ($rvvupData['payments'][0]['status'] !== Method::STATUS_AUTHORIZED) {
                    $this->processorPool->getProcessor($rvvupData['status'])->execute($order, $rvvupData, $origin);
                }
            }

            $processor = $this->processorPool->getProcessor($rvvupData['payments'][0]['status']);
            $result = $processor->execute($order, $rvvupData, $origin);

            if ($redirectUrl) {
                $result->setRedirectPath($redirectUrl);
            }

            $payment = $order->getPayment();
            $dashboardUrl = $rvvupData['dashboardUrl'] ?? '';
            $payment->setAdditionalInformation(Method::DASHBOARD_URL, $dashboardUrl);
            $this->paymentResource->save($payment);

            if (get_class($processor) == Cancel::class) {
                return $this->processResultPage($result, true);
            }
            return $this->processResultPage($result, false);
        } catch (\Exception $e) {
            $this->logger->addRvvupError(
                'Error while processing Rvvup Order status ',
                $e->getMessage(),
                $rvvupId,
                null,
                null,
                $origin
            );

            if (isset($order)) {
                $order->addStatusToHistory(
                    $order->getStatus(),
                    'Failed to update Magento order from Rvvup order status check',
                    false
                );
                $this->orderRepository->save($order);
            }

            return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath(
                In::SUCCESS,
                ['_secure' => true]
            );
        }
    }

    /**
     * @param ProcessOrderResultInterface $result
     * @param bool $restoreQuote
     * @return Redirect
     */
    private function processResultPage(ProcessOrderResultInterface $result, bool $restoreQuote): Redirect
    {
        if ($restoreQuote) {
            $this->checkoutSession->restoreQuote();
        }

        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $params = ['_secure' => true];

        // If specifically we are redirecting the user to the checkout page,
        // set the redirect to the payment step
        // and set the messages to be added to the custom group.
        if ($result->getRedirectPath() === IN::FAILURE) {
            $params['_fragment'] = 'payment';
            $messageGroup = SessionMessageInterface::MESSAGE_GROUP;
        }

        $result->setSessionMessage($messageGroup ?? null);

        $redirect->setPath($result->getRedirectPath(), $params);

        return $redirect;
    }
}
