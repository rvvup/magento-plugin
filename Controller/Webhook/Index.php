<?php declare(strict_types=1);

namespace Rvvup\Payments\Controller\Webhook;

use Exception;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\Payment\PaymentDataGetInterface;
use Rvvup\Payments\Model\ProcessOrder\ProcessorPool;

/**
 * The purpose of this controller is to accept incoming webhooks from Rvvup to update the status of payments
 * that are not yet complete and to either finalise, or cancel them.
 */
class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /** @var RequestInterface */
    private $request;
    /** @var ResultFactory */
    private $resultFactory;
    /** @var ConfigInterface */
    private $config;
    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;
    /** @var OrderPaymentRepositoryInterface */
    private $orderPaymentRepository;
    /** @var OrderRepositoryInterface */
    private $orderRepository;
    /** @var PaymentDataGetInterface */
    private $paymentDataGet;
    /** @var ProcessorPool */
    private $processorPool;

    /**
     * Set via di.xml
     *
     * @var LoggerInterface|RvvupLog
     */
    private $logger;

    /**
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param ConfigInterface $config
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentDataGetInterface $paymentDataGet
     * @param ProcessorPool $processorPool
     * @param LoggerInterface $logger
     */
    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        ConfigInterface $config,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        OrderRepositoryInterface $orderRepository,
        PaymentDataGetInterface $paymentDataGet,
        ProcessorPool $processorPool,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->config = $config;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->orderRepository = $orderRepository;
        $this->paymentDataGet = $paymentDataGet;
        $this->processorPool = $processorPool;
        $this->logger = $logger;
    }

    /**
     * ToDO: Move process logic in service running in queue. Controller should just store data and return success.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $merchantId = $this->request->getParam('merchant_id', false);
        $rvvupOrderId = $this->request->getParam('order_id', false);

        try {
            // Ensure required params are present
            if (!$merchantId || !$rvvupOrderId) {
                /**
                 * If one of these values is missing the request is likely not from the Rvvup backend
                 * so returning a 400 should be fine to indicate the request is invalid and won't cause
                 * Rvvup to make repeated requests to the webhook.
                 */
                return $this->returnInvalidResponse();
            }

            // Ensure configured merchant_id matches request
            if ($merchantId !== $this->config->getMerchantId()) {
                /**
                 * The configuration in Magento is different from the webhook. We don't want Rvvup's backend to
                 * continually make repeated calls so return a 200 and log the issue.
                 */
                $this->logger->warning("`merchant_id` from webhook does not match configuration");
                return $this->returnSuccessfulResponse();
            }

            // Saerch for the payment record by the Rvvup order ID which is stored in the credit card field.
            $searchCriteria = $this->searchCriteriaBuilder->addFilter(
                OrderPaymentInterface::CC_TRANS_ID,
                $rvvupOrderId
            )->create();

            $resultSet = $this->orderPaymentRepository->getList($searchCriteria);

            // We always expect 1 payment object for a Rvvup Order ID.
            // Otherwise, this could be a malicious attempt, so log issue & return 200.
            if ($resultSet->getTotalCount() !== 1) {
                $this->logger->warning('Webhook error. Payment not found for order.', [
                    'rvvup_order_id' => $rvvupOrderId,
                    'payments_count' => $resultSet->getTotalCount()
                ]);

                return $this->returnSuccessfulResponse();
            }

            $payments = $resultSet->getItems();

            /** @var \Magento\Sales\Api\Data\OrderPaymentInterface $payment */
            $payment = reset($payments);

            $order = $this->orderRepository->get($payment->getParentId());

            // if Payment method is not Rvvup, continue.
            if (stripos($payment->getMethod(), 'rvvup_') !== 0) {
                return $this->returnSuccessfulResponse();
            }

            $rvvupData = $this->paymentDataGet->execute($rvvupOrderId);

            if (empty($rvvupData) || !isset($rvvupData['status'])) {
                $this->logger->error('Webhook error. Rvvup order data could not be fetched.', [
                    'rvvup_order_id' => $rvvupOrderId
                ]);

                return $this->returnExceptionResponse();
            }

            $this->processorPool->getProcessor($rvvupData['status'])->execute($order, $rvvupData);

            return $this->returnSuccessfulResponse();
        } catch (Exception $e) {
            $this->logger->debug('Webhook exception:' . $e->getMessage(), [
                'order_id' => $rvvupOrderId,
            ]);

            return $this->returnExceptionResponse();
        }
    }

    /**
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @return ResultInterface
     */
    private function returnSuccessfulResponse(): ResultInterface
    {
        $response = $this->resultFactory->create($this->resultFactory::TYPE_RAW);
        $response->setHttpResponseCode(200);

        return $response;
    }

    /**
     * @return ResultInterface
     */
    private function returnInvalidResponse(): ResultInterface
    {
        $response = $this->resultFactory->create($this->resultFactory::TYPE_RAW);
        $response->setHttpResponseCode(400);

        return $response;
    }

    private function returnExceptionResponse(): ResultInterface
    {
        $response = $this->resultFactory->create($this->resultFactory::TYPE_RAW);
        $response->setHttpResponseCode(500);

        return $response;
    }
}
