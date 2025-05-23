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
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\App\Request\StorePathInfoValidator;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Exception\PaymentValidationException;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Config\RvvupConfigurationInterface;
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

    /** @var RvvupConfigurationInterface */
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
     * @param RvvupConfigurationInterface $config
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
        RvvupConfigurationInterface $config,
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
        $applicationSource = $this->request->getParam('application_source', false) ?? "MAGENTO_CHECKOUT";
        $storeId = null;

        try {
            if (!$merchantId) {
                return $this->returnInvalidResponse('Merchant id is not present', []);
            }
            list($quote, $order) = $this->orderOrQuoteResolver($rvvupOrderId, $paymentLinkId, $checkoutId);

            $storeId = $this->getStoreId($quote, $order);

            // Merchant ID does not match, no need to process
            if ($merchantId !== $this->config->getMerchantId($storeId)) {
                return $this->returnSkipResponse(
                    'Invalid merchant id',
                    [
                        'merchant_id' => $merchantId,
                        'config_merchant_id' => $this->config->getMerchantId($storeId),
                        'rvvup_id' => $rvvupOrderId
                    ]
                );
            }

            if ($eventType == WebhookEventType::REFUND_COMPLETED) {
                return $this->processRefundCompleted($rvvupOrderId, $merchantId, $refundId, $storeId);
            } elseif ($eventType == WebhookEventType::PAYMENT_AUTHORIZED) {
                return $this->processPayment(
                    $eventType,
                    $rvvupOrderId,
                    $merchantId,
                    $paymentId,
                    $storeId,
                    $paymentLinkId,
                    $checkoutId,
                    $applicationSource,
                    $quote,
                    $order
                );
            } elseif ($eventType == WebhookEventType::PAYMENT_COMPLETED) {
                return $this->processPayment(
                    $eventType,
                    $rvvupOrderId,
                    $merchantId,
                    $paymentId,
                    $storeId,
                    $paymentLinkId,
                    $checkoutId,
                    $applicationSource,
                    $quote,
                    $order
                );
            }

            return $this->returnSkipResponse("Event type not supported", []);
        } catch (Exception $e) {
            $this->logger->error('Webhook exception:' . $e->getMessage(), [
                'merchant_id' => $merchantId,
                'event_type' => $eventType,
                'order_id' => $rvvupOrderId,
                'resolved_store_id' => $storeId
            ]);
            return $this->returnExceptionResponse('Internal Server Exception', ['cause' => $e->getMessage()]);
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
     * @param string $reason
     * @param array $metadata
     * @return ResultInterface
     */
    private function returnExceptionResponse(string $reason, array $metadata): ResultInterface
    {
        $response = $this->resultFactory->create($this->resultFactory::TYPE_JSON);
        $response->setHttpResponseCode(500);
        $response->setData(['reason' => $reason, 'metadata' => $metadata]);

        return $response;
    }

    /**
     * @param Quote|null $quote
     * @param OrderInterface|null $order
     * @return string
     * @throws NoSuchEntityException
     */
    private function getStoreId(?Quote $quote, ?OrderInterface $order): string
    {
        if (isset($quote)) {
            return (string) $quote->getStoreId();
        }
        if (isset($order) && $order->getId()) {
            return (string) $order->getStoreId();
        }

        return (string) $this->storeManager->getStore()->getId();
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
            try {
                $order = $this->captureService->getOrderByRvvupId($rvvupOrderId);
            } catch (PaymentValidationException $e) {
                return [null, null];
            }
        }
        if (isset($order)) {
            return [null, $order];
        }
        return [null, null];
    }

    /**
     * @param string|boolean $rvvupOrderId
     * @param string|boolean $merchantId
     * @param string|boolean $refundId
     * @param string|null $storeId
     * @return ResultInterface
     * @throws LocalizedException
     */
    private function processRefundCompleted($rvvupOrderId, $merchantId, $refundId, ?string $storeId): ResultInterface
    {
        $payload = [
            'order_id' => $rvvupOrderId,
            'merchant_id' => $merchantId,
            'refund_id' => $refundId,
            'store_id' => $storeId,
        ];

        if (!$rvvupOrderId || !$refundId) {
            return $this->returnInvalidResponse('Missing parameters required for REFUND_COMPLETED', $payload);
        }
        $this->refundPool->getProcessor(WebhookEventType::REFUND_COMPLETED)->execute($payload);
        return $this->returnSuccessfulResponse();
    }

    /**
     * @param string $eventType
     * @param string|boolean $rvvupOrderId
     * @param string|boolean $merchantId
     * @param string|boolean $paymentId
     * @param string|null $storeId
     * @param string|boolean $paymentLinkId
     * @param string|boolean $checkoutId
     * @param string|boolean $applicationSource
     * @param Quote|null $quote
     * @param OrderInterface|null $order
     * @return ResultInterface
     * @throws AlreadyExistsException
     */
    private function processPayment(
        string          $eventType,
        $rvvupOrderId,
        $merchantId,
        $paymentId,
        ?string         $storeId,
        $paymentLinkId,
        $checkoutId,
        $applicationSource,
        ?Quote          $quote,
        ?OrderInterface $order
    ): ResultInterface {
        $payload = [
            'order_id' => $rvvupOrderId,
            'merchant_id' => $merchantId,
            'payment_id' => $paymentId,
            'event_type' => $eventType,
            'store_id' => $storeId,
            'payment_link_id' => $paymentLinkId,
            'checkout_id' => $checkoutId,
            'application_source' => $applicationSource,
            'origin' => 'webhook'
        ];
        if (!$rvvupOrderId) {
            return $this->returnInvalidResponse('Missing parameters required for ' . $eventType, $payload);
        }
        if (isset($quote)) {
            $payload['quote_id'] = $quote->getId();
        } elseif (isset($order)) {
            $payload['magento_order_id'] = $order->getId();
        } else {
            return $this->returnSkipResponse('Order/quote not found for ' . $eventType, []);
        }
        $this->webhookRepository->addToWebhookQueue($payload);
        return $this->returnSuccessfulResponse();
    }
}
