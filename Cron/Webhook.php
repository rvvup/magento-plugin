<?php
declare(strict_types=1);

namespace Rvvup\Payments\Cron;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\ResourceModel\WebhookModel\WebhookCollection;
use Rvvup\Payments\Model\ResourceModel\WebhookModel\WebhookCollectionFactory;
use Rvvup\Payments\Model\WebhookRepository;

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

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param WebhookCollectionFactory $webhookCollectionFactory
     * @param PublisherInterface $publisher
     * @param Json $json
     * @param WebhookRepository $webhookRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        WebhookCollectionFactory $webhookCollectionFactory,
        PublisherInterface       $publisher,
        Json                     $json,
        WebhookRepository        $webhookRepository,
        LoggerInterface          $logger
    ) {
        $this->webhookCollectionFactory = $webhookCollectionFactory;
        $this->json = $json;
        $this->publisher = $publisher;
        $this->webhookRepository = $webhookRepository;
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
            ->addFieldToFilter('created_at', ['lt' => $date]);
        $this->processWebhooks($collection);
    }

    /**
     * @param WebhookCollection $collection
     * @return void
     * @throws AlreadyExistsException|NoSuchEntityException
     */
    private function processWebhooks(WebhookCollection $collection): void
    {
        $uniquePayloads = [];
        foreach ($collection->getItems() as $item) {
            try {
                $payload = $item->getData('payload');
                $webhookId = (int) $item->getData('webhook_id');
                /** Process only unique payloads per each cron run in order to avoid the locks */
                if (!in_array($payload, $uniquePayloads)) {
                    $uniquePayloads[] = $payload;
                    $this->addWebhookToQueue($webhookId);
                } else {
                    $this->webhookRepository->updateWebhookQueueToProcessed($webhookId);
                }
            } catch (\Exception $exception) {
                $this->webhookRepository->updateWebhookQueueToProcessed((int) $item->getData('webhook_id'));
                $this->logger->addRvvupError(
                    'Failed to process Rvvup webhook:' . $item->getData('payload'),
                    $exception->getMessage()
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
        $this->publisher->publish('rvvup.webhook', $this->json->serialize(['id' => (string)$webhookId]));
        $this->webhookRepository->updateWebhookQueueToProcessed($webhookId);
    }
}
