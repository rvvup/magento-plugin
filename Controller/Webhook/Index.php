<?php declare(strict_types=1);

namespace Rvvup\Payments\Controller\Webhook;

use Exception;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Rvvup\Payments\Model\ConfigInterface;

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
    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /**
     * Set via di.xml
     *
     * @var LoggerInterface|RvvupLog
     */
    private $logger;

    /** @var TransactionRepositoryInterface */
    private $transactionRespository;

    /**
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @param ConfigInterface $config
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     * @param TransactionRepositoryInterface $transactionRepository
     */
    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        ConfigInterface $config,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger,
        TransactionRepositoryInterface $transactionRepository
    ) {
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->config = $config;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->transactionRespository = $transactionRepository;
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $merchantId = $this->request->getParam('merchant_id', false);
        $rvvupOrderId = $this->request->getParam('order_id', false);
        $response = $this->resultFactory->create($this->resultFactory::TYPE_RAW);

        try {
            // Ensure required params are present
            if (!$merchantId || !$rvvupOrderId) {
                /**
                 * If one of these values is missing the request is likely not from the Rvvup backend
                 * so returning a 400 should be fine to indicate the request is invalid and won't cause
                 * Rvvup to make repeated requests to the webhook.
                 */
                $response->setHttpResponseCode(400);
                return $response;
            }

            // Ensure configured merchant_id matches request
            if ($merchantId !== $this->config->getMerchantId()) {
                /**
                 * The configuration in Magento is different from the webhook. We don't want Rvvup's backend to
                 * continually make repeated calls so return a 200 and log the issue.
                 */
                $response->setHttpResponseCode(200);
                $this->logger->warning("`merchant_id` from webhook does not match configuration");
                return $response;
            }

            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('txn_id', $rvvupOrderId)->create();
            $transactions = $this->transactionRespository->getList($searchCriteria)->getItems();
            /** @var Order $order */
            $transaction = reset($transactions);

            $transaction->getOrder()->getPayment()->update(true);
            $this->orderRepository->save($transaction->getOrder());
            return $response;
        } catch (Exception $e) {
            $this->logger->debug('Webhook exception:' . $e->getMessage(), [
                'order_id' => $rvvupOrderId,
            ]);

            $response->setHttpResponseCode(500);
            return $response;
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
}
