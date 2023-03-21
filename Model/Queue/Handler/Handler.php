<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\Queue\Handler;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\WebhookRepositoryInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\Payment\PaymentDataGetInterface;
use Rvvup\Payments\Model\ProcessOrder\ProcessorPool;

class Handler
{
    /** @var WebhookRepositoryInterface */
    private $webhookRepository;
    /** @var SerializerInterface */
    private $serializer;
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
    /** @var LoggerInterface */
    private $logger;

    /**
     * @param WebhookRepositoryInterface $webhookRepository
     * @param SerializerInterface $serializer
     * @param ConfigInterface $config
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param PaymentDataGetInterface $paymentDataGet
     * @param ProcessorPool $processorPool
     * @param LoggerInterface $logger
     */
    public function __construct(
        WebhookRepositoryInterface $webhookRepository,
        SerializerInterface $serializer,
        ConfigInterface $config,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderPaymentRepositoryInterface $orderPaymentRepository,
        OrderRepositoryInterface $orderRepository,
        PaymentDataGetInterface $paymentDataGet,
        ProcessorPool $processorPool,
        LoggerInterface $logger
    ) {
        $this->webhookRepository = $webhookRepository;
        $this->serializer = $serializer;
        $this->config = $config;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderPaymentRepository = $orderPaymentRepository;
        $this->orderRepository = $orderRepository;
        $this->paymentDataGet = $paymentDataGet;
        $this->processorPool = $processorPool;
        $this->logger = $logger;
    }

    /**
     * @param int $id
     * @return void
     */
    public function execute(int $id)
    {
        try {
            $webhook = $this->webhookRepository->getById($id);
            $payload = $this->serializer->unserialize($webhook->getPayload());
            // Ensure required params are present

            // Ensure configured merchant_id matches request
            if ($payload['merchant_id'] !== $this->config->getMerchantId()) {
                /**
                 * The configuration in Magento is different from the webhook. We don't want Rvvup's backend to
                 * continually make repeated calls so return a 200 and log the issue.
                 */
                $this->logger->warning("`merchant_id` from webhook does not match configuration");
                return;
            }

            $rvvupOrderId = $payload['order_id'];
            // Saerch for the payment record by the Rvvup order ID which is stored in the credit card field.
            $searchCriteria = $this->searchCriteriaBuilder->addFilter(
                OrderPaymentInterface::CC_TRANS_ID,
                $rvvupOrderId
            )->create();

            $resultSet = $this->orderPaymentRepository->getList($searchCriteria);

            // We always expect 1 payment object for a Rvvup Order ID.
            if ($resultSet->getTotalCount() !== 1) {
                $this->logger->warning('Webhook error. Payment not found for order.', [
                    'rvvup_order_id' => $rvvupOrderId,
                    'payments_count' => $resultSet->getTotalCount()
                ]);
            }

            $payments = $resultSet->getItems();
            /** @var \Magento\Sales\Api\Data\OrderPaymentInterface $payment */
            $payment = reset($payments);
            $order = $this->orderRepository->get($payment->getParentId());

            // if Payment method is not Rvvup, exit.
            if (strpos($payment->getMethod(), Method::PAYMENT_TITLE_PREFIX) !== 0) {
                return;
            }

            $rvvupData = $this->paymentDataGet->execute($rvvupOrderId);

            if (empty($rvvupData) || !isset($rvvupData['payments'][0]['status'])) {
                $this->logger->error('Webhook error. Rvvup order data could not be fetched.', [
                    'rvvup_order_id' => $rvvupOrderId
                ]);
                return;
            }

            $this->processorPool->getProcessor($rvvupData['payments'][0]['status'])->execute($order, $rvvupData);

        } catch (\Exception $e) {
            $this->logger->debug('Webhook exception:' . $e->getMessage(), [
                'order_id' => $rvvupOrderId,
            ]);
        }
    }
}
