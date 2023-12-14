<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\Queue\Handler;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\WebhookRepositoryInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\Payment\PaymentDataGetInterface;
use Rvvup\Payments\Model\ProcessOrder\ProcessorPool;
use Rvvup\Payments\Service\Order;

class Handler
{

    /** @var WebhookRepositoryInterface */
    private $webhookRepository;
    /** @var SerializerInterface */
    private $serializer;
    /** @var ConfigInterface */
    private $config;
    /** @var SearchCriteriaBuilder */
    private $paymentDataGet;
    /** @var ProcessorPool */
    private $processorPool;
    /** @var LoggerInterface */
    private $logger;

    /**
     * @var Order
     */
    private $orderService;

    /**
     * @param WebhookRepositoryInterface $webhookRepository
     * @param SerializerInterface $serializer
     * @param ConfigInterface $config
     * @param PaymentDataGetInterface $paymentDataGet
     * @param ProcessorPool $processorPool
     * @param LoggerInterface $logger
     * @param Order $orderService
     */
    public function __construct(
        WebhookRepositoryInterface $webhookRepository,
        SerializerInterface $serializer,
        ConfigInterface $config,
        PaymentDataGetInterface $paymentDataGet,
        ProcessorPool $processorPool,
        LoggerInterface $logger,
        Order $orderService
    ) {
        $this->webhookRepository = $webhookRepository;
        $this->serializer = $serializer;
        $this->config = $config;
        $this->paymentDataGet = $paymentDataGet;
        $this->processorPool = $processorPool;
        $this->orderService = $orderService;
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

            $rvvupOrderId = $payload['order_id'];

            $order = $this->orderService->getOrderByRvvupId($rvvupOrderId);

            // if Payment method is not Rvvup, exit.
            if (strpos($order->getPayment()->getMethod(), Method::PAYMENT_TITLE_PREFIX) !== 0) {
                return;
            }

            if (isset($rvvupOrderId)) {
                $rvvupData = $this->paymentDataGet->execute($rvvupOrderId);
                if (empty($rvvupData) || !isset($rvvupData['payments'][0]['status'])) {
                    $this->logger->error('Webhook error. Rvvup order data could not be fetched.', [
                        'rvvup_order_id' => $rvvupOrderId
                    ]);
                    return;
                }
                $this->processorPool->getProcessor($rvvupData['payments'][0]['status'])->execute(
                    $order,
                    $rvvupData
                );
            }

            return;
        } catch (\Exception $e) {
            $this->logger->error('Queue handling exception:' . $e->getMessage(), [
                'order_id' => $rvvupOrderId,
            ]);
        }
    }
}
