<?php
declare(strict_types=1);

namespace Rvvup\Payments\Controller\Webhook;

use Exception;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\App\Request\StorePathInfoValidator;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\ProcessRefund\ProcessorPool as RefundPool;
use Rvvup\Payments\Model\Webhook\WebhookEventType;
use Rvvup\Payments\Model\WebhookRepository;
use Rvvup\Payments\Service\Capture;

/**
 * The purpose of this controller is to accept incoming webhooks from Rvvup to update the status of payments
 * that are not yet complete and to either finalise, or cancel them.
 */
class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /** @var RequestInterface */
    private $request;

    /** @var ConfigInterface */
    private $config;

    /** @var ResultFactory */
    private $resultFactory;

    /** @var WebhookRepository */
    private $webhookRepository;

    /**
     * Set via di.xml
     *
     * @var LoggerInterface|RvvupLog
     */
    private $logger;

    /** @var RefundPool */
    private $refundPool;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var StorePathInfoValidator */
    private $storePathInfoValidator;

    /** @var Http */
    private $http;

    /** @var StoreRepositoryInterface */
    private $storeRepository;

    /** @var Capture  */
    private $captureService;

    /**
     * @param RequestInterface $request
     * @param StoreRepositoryInterface $storeRepository
     * @param Http $http
     * @param ConfigInterface $config
     * @param ResultFactory $resultFactory
     * @param LoggerInterface $logger
     * @param WebhookRepository $webhookRepository
     * @param StoreManagerInterface $storeManager
     * @param StorePathInfoValidator $storePathInfoValidator
     * @param RefundPool $refundPool
     * @param Capture $captureService
     */
    public function __construct(
        RequestInterface         $request,
        StoreRepositoryInterface $storeRepository,
        Http                     $http,
        ConfigInterface          $config,
        ResultFactory            $resultFactory,
        LoggerInterface          $logger,
        WebhookRepository        $webhookRepository,
        StoreManagerInterface    $storeManager,
        StorePathInfoValidator   $storePathInfoValidator,
        RefundPool               $refundPool,
        Capture                  $captureService
    ) {
        $this->request = $request;
        $this->config = $config;
        $this->resultFactory = $resultFactory;
        $this->logger = $logger;
        $this->webhookRepository = $webhookRepository;
        $this->storeManager = $storeManager;
        $this->storePathInfoValidator = $storePathInfoValidator;
        $this->http = $http;
        $this->storeRepository = $storeRepository;
        $this->refundPool = $refundPool;
        $this->captureService = $captureService;
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $merchantId = $this->request->getParam('merchant_id', false);
        $rvvupOrderId = $this->request->getParam('order_id', false);
        $eventType = $this->request->getParam('event_type', false);
        $paymentId = $this->request->getParam('payment_id', false);
        $refundId = $this->request->getParam('refund_id', false);
        $paymentLinkId = $this->request->getParam('payment_link_id', false);
        $checkoutId = $this->request->getParam('checkout_id', false);
        $storeId = null;

        try {
            if (!$merchantId) {
                return $this->returnInvalidResponse('Merchant id is not present', []);
            }
            list($quote, $order) = $this->orderOrQuoteResolver($rvvupOrderId, $paymentLinkId, $checkoutId);

            $storeId = $this->getStoreId($quote, $order);

            // Merchant ID does not match, no need to process
            if ($merchantId !== $this->config->getMerchantId()) {
                return $this->returnSkipResponse(
                    'Invalid merchant id',
                    [
                        'merchant_id' => $merchantId,
                        'config_merchant_id' => $this->config->getMerchantId(),
                        'rvvup_id' => $rvvupOrderId
                    ]
                );
            }

            if ($eventType == WebhookEventType::REFUND_COMPLETED) {
                $payload = [
                    'order_id' => $rvvupOrderId,
                    'merchant_id' => $merchantId,
                    'refund_id' => $refundId,
                    'store_id' => $storeId,
                ];

                if (!$rvvupOrderId || !$refundId) {
                    return $this->returnInvalidResponse('Missing parameters required for ' . $eventType, $payload);
                }
                $this->refundPool->getProcessor($eventType)->execute($payload);
                return $this->returnSuccessfulResponse();
            } elseif ($eventType == WebhookEventType::PAYMENT_COMPLETED ||
                $eventType == WebhookEventType::PAYMENT_AUTHORIZED
            ) {
                $payload = [
                    'order_id' => $rvvupOrderId,
                    'merchant_id' => $merchantId,
                    'payment_id' => $paymentId,
                    'event_type' => $eventType,
                    'store_id' => $storeId,
                    'payment_link_id' => $paymentLinkId,
                    'checkout_id' => $checkoutId,
                    'origin' => 'webhook'
                ];
                if (isset($quote)) {
                    $payload['quote_id'] = $quote->getId();
                } elseif (isset($order)) {
                    $payload['magento_order_id'] = $order->getId();
                }
                if (!$rvvupOrderId) {
                    return $this->returnInvalidResponse('Missing parameters required for ' . $eventType, $payload);
                }
                $this->webhookRepository->addToWebhookQueue($payload);
                return $this->returnSuccessfulResponse();
            }

            return $this->returnSuccessfulResponse();
        } catch (Exception $e) {
            $this->logger->error('Webhook exception:' . $e->getMessage(), [
                'merchant_id' => $merchantId,
                'event_type' => $eventType,
                'order_id' => $rvvupOrderId,
                'resolved_store_id' => $storeId
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
        /**
         * https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/202
         * 202 Accepted: request has been accepted for processing, but the processing has not been completed
         */
        $response->setHttpResponseCode(202);

        return $response;
    }

    /**
     * @param string $reason
     * @param array $metadata
     * @return ResultInterface
     */
    private function returnSkipResponse(string $reason, array $metadata): ResultInterface
    {
        $response = $this->resultFactory->create($this->resultFactory::TYPE_JSON);
        $response->setHttpResponseCode(210);
        $response->setData(['reason' => $reason, 'metadata' => $metadata]);
        return $response;
    }

    /**
     * @param string $reason
     * @param array $metadata
     * @return ResultInterface
     */
    private function returnInvalidResponse(string $reason, array $metadata): ResultInterface
    {
        $response = $this->resultFactory->create($this->resultFactory::TYPE_JSON);
        $response->setHttpResponseCode(400);
        $response->setData(['reason' => $reason, 'metadata' => $metadata]);
        return $response;
    }

    /**
     * @return ResultInterface
     */
    private function returnExceptionResponse(): ResultInterface
    {
        $response = $this->resultFactory->create($this->resultFactory::TYPE_JSON);
        $response->setHttpResponseCode(500);

        return $response;
    }

    /**
     * @param Quote|null $quote
     * @param OrderInterface|null $order
     * @return int
     * @throws NoSuchEntityException
     */
    private function getStoreId(?Quote $quote, ?OrderInterface $order): int
    {
        if (isset($quote)) {
            return $quote->getStoreId();
        }
        if (isset($order) && $order->getId()) {
            return $order->getStoreId();
        }

        return $this->storeManager->getStore()->getId();
    }

    private function orderOrQuoteResolver($rvvupOrderId, $paymentLinkId, $checkoutId): array
    {
        if (isset($rvvupOrderId) && $rvvupOrderId) {
            $quote = $this->captureService->getQuoteByRvvupId($rvvupOrderId);
            if ($quote && $quote->getId()) {
                return [$quote, null];
            }
        }
        if (isset($paymentLinkId) && $paymentLinkId) {
            $order = $this->captureService->getOrderByPaymentField(Method::PAYMENT_LINK_ID, $paymentLinkId);
        } elseif (isset($checkoutId) && $checkoutId) {
            $order = $this->captureService->getOrderByPaymentField(Method::MOTO_ID, $checkoutId);
        } elseif ($rvvupOrderId) {
            $order = $this->captureService->getOrderByRvvupId($rvvupOrderId);
        }
        if (isset($order)) {
            return [null, $order];
        }
        return [null, null];
    }
}
