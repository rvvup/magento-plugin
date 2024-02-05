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
use Rvvup\Payments\Service\Capture;

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

    /** @var Capture  */
    private $captureService;

    /**
     * @param WebhookRepositoryInterface $webhookRepository
     * @param SerializerInterface $serializer
     * @param ConfigInterface $config
     * @param PaymentDataGetInterface $paymentDataGet
     * @param ProcessorPool $processorPool
     * @param LoggerInterface $logger
     * @param Capture $captureService
     */
    public function __construct(
        WebhookRepositoryInterface $webhookRepository,
        SerializerInterface $serializer,
        ConfigInterface $config,
        PaymentDataGetInterface $paymentDataGet,
        ProcessorPool $processorPool,
        LoggerInterface $logger,
        Capture $captureService
    ) {
        $this->webhookRepository = $webhookRepository;
        $this->serializer = $serializer;
        $this->config = $config;
        $this->paymentDataGet = $paymentDataGet;
        $this->processorPool = $processorPool;
        $this->captureService = $captureService;
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

            if ($payload['event_type'] == Method::STATUS_PAYMENT_AUTHORIZED) {
                $quote = $this->captureService->getQuoteByRvvupId($rvvupOrderId);
                if (!$quote) {
                    $this->logger->debug(
                        'Webhook exception: Can not find quote by rvvupId for authorize payment status',
                        [
                            'order_id' => $rvvupOrderId,
                        ]
                    );
                    return;
                }

                $payment = $quote->getPayment();
                $rvvupPaymentId = $payment->getAdditionalInformation(Method::PAYMENT_ID);
                $lastTransactionId = (string)$payment->getAdditionalInformation(Method::TRANSACTION_ID);
                $validate = $this->captureService->validate($rvvupOrderId, $quote, $lastTransactionId);
                if (!$validate['is_valid']) {
                    if ($validate['redirect_to_cart']) {
                        return;
                    }
                    if ($validate['already_exists']) {
                        return;
                    }
                }
                $this->captureService->setCheckoutMethod($quote);
                $order = $this->captureService->createOrder($rvvupOrderId, $quote);
                $reserved = $order['reserved'];
                $orderId = $order['id'];

                if ($reserved) {
                    return;
                }

                if (!$orderId) {
                    return;
                }

                $this->captureService->paymentCapture(
                    $payment,
                    $lastTransactionId,
                    $rvvupPaymentId,
                    $rvvupOrderId
                );

                return;
            }

            $order = $this->captureService->getOrderByRvvupId($rvvupOrderId);

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
