<?php
declare(strict_types=1);

namespace Rvvup\Payments\Cron;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Controller\Webhook\Index;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\ResourceModel\WebhookModel\WebhookCollection;
use Rvvup\Payments\Model\ResourceModel\WebhookModel\WebhookCollectionFactory;
use Rvvup\Payments\Model\WebhookRepository;
use Rvvup\Payments\Service\Capture;

class Webhook
{
    /** @var WebhookCollectionFactory */
    private $webhookCollectionFactory;

    /** @var Json */
    private $json;

    /** @var PublisherInterface */
    private $publisher;

    /** @var WebhookRepository */
    private $webhookRepository;

    /** @var Capture */
    private $captureService;

    /** @var LoggerInterface */
    private LoggerInterface $logger;

    /**
     * @param WebhookCollectionFactory $webhookCollectionFactory
     * @param PublisherInterface $publisher
     * @param Json $json
     * @param WebhookRepository $webhookRepository
     * @param Capture $captureService
     * @param LoggerInterface $logger
     */
    public function __construct(
        WebhookCollectionFactory $webhookCollectionFactory,
        PublisherInterface       $publisher,
        Json                     $json,
        WebhookRepository        $webhookRepository,
        Capture                  $captureService,
        LoggerInterface          $logger
    ) {
        $this->webhookCollectionFactory = $webhookCollectionFactory;
        $this->json = $json;
        $this->publisher = $publisher;
        $this->webhookRepository = $webhookRepository;
        $this->captureService = $captureService;
        $this->logger = $logger;
    }

    /**
     * @return void
     * @throws AlreadyExistsException|NoSuchEntityException
     */
    public function execute(): void
    {
        /** @var WebhookCollection $collection */
        $collection = $this->webhookCollectionFactory->create();
        $date = date('Y-m-d H:i:s', strtotime('-30 seconds'));
        $collection->addFieldToSelect('*')
            ->addFieldToFilter('is_processed', ['eq' => 'false'])
            ->addFieldToFilter('created_at', ['gt' => $date]);
        $this->processWebhooks($collection);
    }

    /**
     * @param WebhookCollection $collection
     * @return void
     * @throws AlreadyExistsException|NoSuchEntityException
     */
    private function processWebhooks(WebhookCollection $collection): void
    {
        foreach ($collection->getItems() as $item) {
            try {
                $payload = $item->getData('payload');
                $data = $this->json->unserialize($payload);
                $webhookId = (int) $item->getData('webhook_id');
                if (isset($data['store_id'])) {
                    $orderId = $data['order_id'];

                    if (isset($data['payment_link_id']) && $data['payment_link_id']) {
                        if (!$this->validatePaymentLink($data, $webhookId)) {
                            continue;
                        }
                    } elseif (isset($data['checkout_id']) && $data['checkout_id']) {
                        if (!$this->validateMoto($data, $webhookId)) {
                            continue;
                        }
                    } elseif ($data['event_type'] == Index::PAYMENT_COMPLETED) {
                        if (!$this->validatePaymentCompleted($orderId, $webhookId)) {
                            continue;
                        }
                    } elseif ($data['event_type'] == Method::STATUS_PAYMENT_AUTHORIZED) {
                        if (!$this->validatePaymentAuthorized($orderId, $webhookId)) {
                            continue;
                        }
                    }

                    $this->addWebhookToQueue($webhookId);
                } else {
                    $webhook = $this->webhookRepository->getById($webhookId);
                    $webhook->setData('is_processed', true);
                    $this->webhookRepository->save($webhook);
                }
            } catch (\Exception $exception) {
                $webhookId = (int) $item->getData('webhook_id');
                $webhook = $this->webhookRepository->getById($webhookId);
                $webhook->setData('is_processed', true);
                $this->webhookRepository->save($webhook);
                $this->logger->addRvvupError(
                    'Failed to process Rvvup webhook:' . $item->getData($payload),
                    $exception->getMessage(),
                );
            }
        }
    }

    /**
     * @param int $webhookId
     * @return void
     * @throws AlreadyExistsException|NoSuchEntityException
     */
    private function addWebhookToQueue(int $webhookId): void
    {
        $this->publisher->publish(
            'rvvup.webhook',
            $this->json->serialize([
                'id' => (string) $webhookId,
            ])
        );
        $webhook = $this->webhookRepository->getById($webhookId);
        $webhook->setData('is_processed', true);
        $this->webhookRepository->save($webhook);
    }

    /**
     * @param string $orderId
     * @param int $webhookId
     * @return bool
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException
     */
    private function validatePaymentCompleted(string $orderId, int $webhookId): bool
    {
        $orderList = $this->captureService->getOrderListByRvvupId($orderId);
        if ($orderList->getTotalCount() == 1) {
            $items = $orderList->getItems();
            $orderPayment = end($items);
            $order = $orderPayment->getOrder();
            if (!$order) {
                $webhook = $this->webhookRepository->getById($webhookId);
                $webhook->setData('is_processed', true);
                $this->webhookRepository->save($webhook);
                return false;
            }
        }
        return true;
    }

    /**
     * @param string $orderId
     * @param int $webhookId
     * @return bool
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException
     */
    private function validatePaymentAuthorized(string $orderId, int $webhookId): bool
    {
        $quote = $this->captureService->getQuoteByRvvupId($orderId);
        if (!$quote || !$quote->getId()) {
            $webhook = $this->webhookRepository->getById($webhookId);
            $webhook->setData('is_processed', true);
            $this->webhookRepository->save($webhook);
            return false;
        }
        return true;
    }

    /**
     * @param array $data
     * @param int $webhookId
     * @return bool
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException
     */
    private function validateMoto(array $data, int $webhookId): bool
    {
        $order = $this->captureService->getOrderByPaymentField(
            Method::MOTO_ID,
            $data['checkout_id']
        );

        if (!$order || !$order->getId()) {
            $webhook = $this->webhookRepository->getById($webhookId);
            $webhook->setData('is_processed', true);
            $this->webhookRepository->save($webhook);
            return false;
        }

        return true;
    }

    /**
     * @param array $data
     * @param int $webhookId
     * @return bool
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException
     */
    private function validatePaymentLink(array $data, int $webhookId): bool
    {
        $order = $this->captureService->getOrderByPaymentField(
            Method::PAYMENT_LINK_ID,
            $data['payment_link_id']
        );

        if (!$order || !$order->getId()) {
            $webhook = $this->webhookRepository->getById($webhookId);
            $webhook->setData('is_processed', true);
            $this->webhookRepository->save($webhook);
            return false;
        }

        return true;
    }
}
