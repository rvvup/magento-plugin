<?php
declare(strict_types=1);

namespace Rvvup\Payments\Controller\Webhook;

use Exception;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\ProcessRefund\Complete;
use Rvvup\Payments\Model\ProcessRefund\ProcessorPool as RefundPool;
use Rvvup\Payments\Model\WebhookRepository;

/**
 * The purpose of this controller is to accept incoming webhooks from Rvvup to update the status of payments
 * that are not yet complete and to either finalise, or cancel them.
 */
class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const PAYMENT_COMPLETED = 'PAYMENT_COMPLETED';

    /** @var RequestInterface */
    private $request;

    /** @var ConfigInterface */
    private $config;

    /** @var SerializerInterface */
    private $serializer;

    /** @var ResultFactory */
    private $resultFactory;

    /** @var PublisherInterface */
    private $publisher;

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

    /**
     * @param RequestInterface $request
     * @param ConfigInterface $config
     * @param SerializerInterface $serializer
     * @param ResultFactory $resultFactory
     * @param LoggerInterface $logger
     * @param PublisherInterface $publisher
     * @param WebhookRepository $webhookRepository
     * @param RefundPool $refundPool
     */
    public function __construct(
        RequestInterface $request,
        ConfigInterface $config,
        SerializerInterface $serializer,
        ResultFactory $resultFactory,
        LoggerInterface $logger,
        PublisherInterface $publisher,
        WebhookRepository $webhookRepository,
        RefundPool $refundPool
    ) {
        $this->request = $request;
        $this->config = $config;
        $this->serializer = $serializer;
        $this->resultFactory = $resultFactory;
        $this->logger = $logger;
        $this->publisher = $publisher;
        $this->webhookRepository = $webhookRepository;
        $this->refundPool = $refundPool;
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        try {
            $merchantId = $this->request->getParam('merchant_id', false);
            $rvvupOrderId = $this->request->getParam('order_id', false);
            $eventType = $this->request->getParam('event_type', false);
            $paymentId = $this->request->getParam('payment_id', false);
            $refundId = $this->request->getParam('refund_id', false);
            $paymentLinkId = $this->request->getParam('payment_link_id', false);

            // Ensure required params are present
            if (!$merchantId || !$rvvupOrderId) {
                /**
                 * If one of these values is missing the request is likely not from the Rvvup backend
                 * so returning a 400 should be fine to indicate the request is invalid and won't cause
                 * Rvvup to make repeated requests to the webhook.
                 */
                return $this->returnInvalidResponse();
            }

            // Merchant ID does not match, no need to process
            if ($merchantId !== $this->config->getMerchantId()) {
                return $this->returnSkipResponse();
            }

            $payload = [
                'order_id' => $rvvupOrderId,
                'merchant_id' => $merchantId,
                'refund_id' => $refundId,
                'payment_id' => $paymentId,
                'event_type' => $eventType,
                'payment_link_id' => $paymentLinkId
            ];

            if ($payload['event_type'] == Complete::TYPE) {
                $this->refundPool->getProcessor($eventType)->execute($payload);
                return $this->returnSuccessfulResponse();
            } elseif ($payload['event_type'] == self::PAYMENT_COMPLETED ||
                $payload['event_type'] == Method::STATUS_PAYMENT_AUTHORIZED) {
                $webhook = $this->webhookRepository->new(['payload' => $this->serializer->serialize($payload)]);
                $this->webhookRepository->save($webhook);
                $this->publisher->publish('rvvup.webhook', (int)$webhook->getId());
                return $this->returnSuccessfulResponse();
            }

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
        /**
         * https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/202
         * 202 Accepted: request has been accepted for processing, but the processing has not been completed
         */
        $response->setHttpResponseCode(202);

        return $response;
    }

    /**
     * @return ResultInterface
     */
    private function returnSkipResponse(): ResultInterface
    {
        $response = $this->resultFactory->create($this->resultFactory::TYPE_RAW);
        $response->setHttpResponseCode(210);

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
