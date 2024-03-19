<?php
declare(strict_types=1);

namespace Rvvup\Payments\Cron;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Serialize\Serializer\Json;
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

    /**
     * @param WebhookCollectionFactory $webhookCollectionFactory
     * @param PublisherInterface $publisher
     * @param Json $json
     * @param WebhookRepository $webhookRepository
     * @param Capture $captureService
     */
    public function __construct(
        WebhookCollectionFactory $webhookCollectionFactory,
        PublisherInterface       $publisher,
        Json                     $json,
        WebhookRepository        $webhookRepository,
        Capture                  $captureService
    ) {
        $this->webhookCollectionFactory = $webhookCollectionFactory;
        $this->json = $json;
        $this->publisher = $publisher;
        $this->webhookRepository = $webhookRepository;
        $this->captureService = $captureService;
    }

    /**
     * @return void
     * @throws AlreadyExistsException|NoSuchEntityException
     */
    public function execute(): void
    {
        $date = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        $lastAcceptableDate = date('Y-m-d H:i:s', strtotime('-2 minutes'));
        /** @var WebhookCollection $collection */
        $collection = $this->webhookCollectionFactory->create();

        /** We process webhooks only if:
         * they exist longer than 2 minutes
         */
        $collection->addFieldToSelect('*')
            ->addFieldToFilter('created_at', ['gt' => $date])
            ->addFieldToFilter('created_at', ['lt' => $lastAcceptableDate])
            ->addFieldToFilter('is_processed', ['eq' => 'false']);

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
            $payload = $item->getData('payload');
            $data = $this->json->unserialize($payload);
            if (isset($data['store_id'])) {
                $storeId = (int) $data['store_id'];
                $orderId = $data['order_id'];
                $webhookId = (int) $item->getData('webhook_id');

                if ($data['payment_link_id']) {
                    if (!$this->processPaymentLink($storeId, $data, $webhookId)) {
                        continue;
                    }
                } elseif ($data['event_type'] == Index::PAYMENT_COMPLETED) {
                    if (!$this->processPaymentCompleted($orderId, $storeId, $webhookId)) {
                        continue;
                    }
                } elseif ($data['event_type'] == Method::STATUS_PAYMENT_AUTHORIZED) {
                    if (!$this->processPaymentAuthorized($orderId, $storeId, $webhookId)) {
                        continue;
                    }
                }

                $this->addWebhookToQueue($webhookId);
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
     * @param int $storeId
     * @param int $webhookId
     * @return bool
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException
     */
    private function processPaymentCompleted(string $orderId, int $storeId, int $webhookId): bool
    {
        $orderList = $this->captureService->getOrderListByRvvupId($orderId, $storeId);
        if ($orderList->getTotalCount() == 1) {
            $items = $orderList->getItems();
            $order = end($items);
            if ($order->getStoreId() !== $storeId) {
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
     * @param int $storeId
     * @param int $webhookId
     * @return bool
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException
     */
    private function processPaymentAuthorized(string $orderId, int $storeId, int $webhookId): bool
    {
        $quote = $this->captureService->getQuoteByRvvupId($orderId, (string)$storeId);
        if (!$quote || !$quote->getId()) {
            $webhook = $this->webhookRepository->getById($webhookId);
            $webhook->setData('is_processed', true);
            $this->webhookRepository->save($webhook);
            return false;
        }
        return true;
    }

    /**
     * @param int $storeId
     * @param array $data
     * @param int $webhookId
     * @return bool
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException
     */
    private function processPaymentLink(int $storeId, array $data, int $webhookId): bool
    {
        $order = $this->captureService->getOrderByRvvupPaymentLinkId(
            $data['payment_link_id'],
            $storeId
        );

        if (!$order->getId()) {
            $webhook = $this->webhookRepository->getById($webhookId);
            $webhook->setData('is_processed', true);
            $this->webhookRepository->save($webhook);
        }

        return true;
    }
}
