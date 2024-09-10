<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Rvvup\Payments\Api\Data\WebhookInterface;
use Rvvup\Payments\Api\Data\WebhookInterfaceFactory;
use Rvvup\Payments\Api\WebhookRepositoryInterface;
use Rvvup\Payments\Model\ResourceModel\WebhookResource;

class WebhookRepository implements WebhookRepositoryInterface
{
    /** @var WebhookResource */
    private $resource;
    /** @var WebhookInterfaceFactory */
    private $factory;
    /** @var SerializerInterface */
    private $serializer;

    /**
     * @param WebhookResource $resource
     * @param WebhookInterfaceFactory $factory
     * @param SerializerInterface $serializer
     */
    public function __construct(
        WebhookResource $resource,
        WebhookInterfaceFactory $factory,
        SerializerInterface $serializer
    ) {
        $this->serializer = $serializer;
        $this->resource = $resource;
        $this->factory = $factory;
    }

    /**
     * @param array $data
     * @return WebhookInterface
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function addToWebhookQueue(array $data = []): WebhookInterface
    {
        $date = date('Y-m-d H:i:s', strtotime('now'));
        $webhook = $this->new(
            [
                'payload' => $this->serializer->serialize($data),
                'created_at' => $date
            ]
        );

        return $this->save($webhook);
    }

    /**
     * @param int $webhookId
     * @return void
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException
     */
    public function updateWebhookQueueToProcessed(int $webhookId): void
    {
        $webhook = $this->getById($webhookId);
        $webhook->setData('is_processed', true);
        $this->save($webhook);
    }

    /**
     * @param WebhookInterface $webhook
     * @return WebhookInterface
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function save(WebhookInterface $webhook): WebhookInterface
    {
        $this->resource->save($webhook);
        return $webhook;
    }

    /**
     * @param int $id
     * @return WebhookInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $id): WebhookInterface
    {
        $webhook = $this->factory->create();
        $this->resource->load($webhook, $id);
        if (!$webhook->getId()) {
            throw new NoSuchEntityException(__('Unable to find webhook with ID "%1"', $id));
        }
        return $webhook;
    }

    /**
     * @param WebhookInterface $webhook
     * @return WebhookInterface
     * @throws \Exception
     */
    public function delete(WebhookInterface $webhook): WebhookInterface
    {
        $this->resource->delete($webhook);
        return $webhook;
    }

    /**
     * @param array $data
     * @return WebhookInterface
     */
    private function new(array $data = []): WebhookInterface
    {
        $webhook = $this->factory->create(['data'=> $data]);
        $webhook->setDataChanges(true);
        return $webhook;
    }
}
