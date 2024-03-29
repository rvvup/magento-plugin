<?php
declare(strict_types=1);

namespace Rvvup\Payments\Model\Queue\Handler;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\ResourceModel\Order\Payment;
use Magento\Store\Model\App\Emulation;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\WebhookRepositoryInterface;
use Rvvup\Payments\Gateway\Method;
use Magento\Framework\Serialize\Serializer\Json;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\Payment\PaymentDataGetInterface;
use Rvvup\Payments\Model\ProcessOrder\ProcessorPool;
use Rvvup\Payments\Model\RvvupConfigProvider;
use Rvvup\Payments\Service\Cache;
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

    /** @var Payment */
    private $paymentResource;

    /** @var Cache */
    private $cacheService;

    /** @var Json */
    private $json;

    /** @var Emulation */
    private $emulation;

    /**
     * @param WebhookRepositoryInterface $webhookRepository
     * @param SerializerInterface $serializer
     * @param ConfigInterface $config
     * @param PaymentDataGetInterface $paymentDataGet
     * @param ProcessorPool $processorPool
     * @param LoggerInterface $logger
     * @param Payment $paymentResource
     * @param Cache $cacheService
     * @param Capture $captureService
     * @param Emulation $emulation
     * @param Json $json
     */
    public function __construct(
        WebhookRepositoryInterface $webhookRepository,
        SerializerInterface $serializer,
        ConfigInterface $config,
        PaymentDataGetInterface $paymentDataGet,
        ProcessorPool $processorPool,
        LoggerInterface $logger,
        Payment $paymentResource,
        Cache $cacheService,
        Capture $captureService,
        Emulation $emulation,
        Json $json
    ) {
        $this->webhookRepository = $webhookRepository;
        $this->serializer = $serializer;
        $this->config = $config;
        $this->paymentDataGet = $paymentDataGet;
        $this->processorPool = $processorPool;
        $this->captureService = $captureService;
        $this->paymentResource = $paymentResource;
        $this->cacheService = $cacheService;
        $this->logger = $logger;
        $this->emulation = $emulation;
        $this->json = $json;
    }

    /**
     * @param string $data
     * @return void
     */
    public function execute(string $data)
    {
        try {
            $data = $this->json->unserialize($data);

            $webhook = $this->webhookRepository->getById((int)$data['id']);
            $payload = $this->serializer->unserialize($webhook->getPayload());

            $rvvupOrderId = $payload['order_id'];
            $rvvupPaymentId = $payload['payment_id'];
            $storeId = $payload['store_id'] ?? false;

            if (!$storeId) {
                return;
            }

            $this->emulation->startEnvironmentEmulation((int) $storeId);

            if ($paymentLinkId = $payload['payment_link_id']) {
                $order = $this->captureService->getOrderByRvvupPaymentLinkId($paymentLinkId, $storeId);
                if ($order && $order->getId()) {
                    $this->processOrder($order, $rvvupOrderId, $rvvupPaymentId);
                    return;
                }
                return;
            }

            if ($payload['event_type'] == Method::STATUS_PAYMENT_AUTHORIZED) {
                $quote = $this->captureService->getQuoteByRvvupId($rvvupOrderId, $storeId);
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
                $validate = $this->captureService->validate($quote, $lastTransactionId, $rvvupOrderId);
                if (!$validate->getIsValid()) {
                    if ($validate->getRedirectToCart()) {
                        return;
                    }
                    if ($validate->getAlreadyExists()) {
                        return;
                    }
                }
                $this->captureService->setCheckoutMethod($quote);
                $validation = $this->captureService->createOrder($rvvupOrderId, $quote);
                $alreadyExists = $validation->getAlreadyExists();
                $orderId = $validation->getOrderId();

                if ($alreadyExists) {
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
            if ($order && $order->getId()) {
                $this->processOrder($order, $rvvupOrderId, $rvvupPaymentId);
            }
            return;
        } catch (\Exception $e) {
            $this->logger->error('Queue handling exception:' . $e->getMessage(), [
                'order_id' => $rvvupOrderId,
            ]);
        }
    }

    /**
     * @param OrderInterface $order
     * @param string $rvvupOrderId
     * @param string $rvvupPaymentId
     * @return void
     * @throws LocalizedException
     * @throws AlreadyExistsException
     */
    private function processOrder(OrderInterface $order, string $rvvupOrderId, string $rvvupPaymentId): void
    {
        // if Payment method is not Rvvup, exit.
        if (strpos($order->getPayment()->getMethod(), Method::PAYMENT_TITLE_PREFIX) !== 0) {
            if (strpos($order->getPayment()->getMethod(), RvvupConfigProvider::CODE) !== 0) {
                return;
            }
        }

        $rvvupData = $this->paymentDataGet->execute($rvvupOrderId);
        if (empty($rvvupData) || !isset($rvvupData['payments'][0]['status'])) {
            $this->logger->error('Webhook error. Rvvup order data could not be fetched.', [
                    'rvvup_order_id' => $rvvupOrderId
                ]);
            return;
        }
        $payment = $order->getPayment();
        $payment->setAdditionalInformation(Method::ORDER_ID, $rvvupOrderId);
        $payment->setAdditionalInformation(Method::PAYMENT_ID, $rvvupPaymentId);
        $this->paymentResource->save($payment);
        $this->cacheService->clear($rvvupOrderId, $order->getState());
        if ($order->getPayment()->getMethod() == RvvupConfigProvider::CODE) {
            $this->processorPool->getPaymentLinkProcessor($rvvupData['payments'][0]['status'])->execute(
                $order,
                $rvvupData
            );
        } else {
            $this->processorPool->getProcessor($rvvupData['payments'][0]['status'])->execute(
                $order,
                $rvvupData
            );
        }
    }
}
