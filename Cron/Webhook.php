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
        /** @var WebhookCollection $collection */
        $collection = $this->webhookCollectionFactory->create();
        $collection->addFieldToSelect('*')
            ->addFieldToFilter('is_processed', ['eq' => 'false']);
        $collection->clear();

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
            $webhookId = (int) $item->getData('webhook_id');
            if (isset($data['store_id'])) {
                $storeId = (int) $data['store_id'];
                $orderId = $data['order_id'];

                if (isset($data['payment_link_id']) && $data['payment_link_id']) {
                    if (!$this->validatePaymentLink($storeId, $data, $webhookId)) {
                        continue;
                    }
                } elseif (isset($data['checkout_id']) && $data['checkout_id']) {
                    if (!$this->validateMoto($storeId, $data, $webhookId)) {
                        continue;
                    }
                } elseif ($data['event_type'] == Index::PAYMENT_COMPLETED) {
                    if (!$this->validatePaymentCompleted($orderId, $storeId, $webhookId)) {
                        continue;
                    }
                } elseif ($data['event_type'] == Method::STATUS_PAYMENT_AUTHORIZED) {
                    if (!$this->validatePaymentAuthorized($orderId, $storeId, $webhookId)) {
                        continue;
                    }
                }

                $this->addWebhookToQueue($webhookId);
            } else {
                $webhook = $this->webhookRepository->getById($webhookId);
                $webhook->setData('is_processed', true);
                $this->webhookRepository->save($webhook);
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
    private function validatePaymentCompleted(string $orderId, int $storeId, int $webhookId): bool
    {
        $orderList = $this->captureService->getOrderListByRvvupId($orderId);
        if ($orderList->getTotalCount() == 1) {
            $items = $orderList->getItems();
            $orderPayment = end($items);
            $order = $orderPayment->getOrder();
            if (!$order || (int)$order->getStoreId() !== $storeId) {
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
    private function validatePaymentAuthorized(string $orderId, int $storeId, int $webhookId): bool
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
    private function validateMoto(int $storeId, array $data, int $webhookId): bool
    {
        $order = $this->captureService->getOrderByPaymentField(
            'rvvup_moto_id',
            $data['checkout_id'],
            (string)$storeId
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
     * @param int $storeId
     * @param array $data
     * @param int $webhookId
     * @return bool
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException
     */
    private function validatePaymentLink(int $storeId, array $data, int $webhookId): bool
    {
        $order = $this->captureService->getOrderByPaymentField(
            'rvvup_payment_link_id',
            $data['payment_link_id'],
            (string)$storeId
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
