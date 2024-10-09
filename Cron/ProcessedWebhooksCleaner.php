<?php
declare(strict_types=1);

namespace Rvvup\Payments\Cron;

use Magento\Framework\Exception\LocalizedException;
use Rvvup\Payments\Model\ResourceModel\WebhookResource;

class ProcessedWebhooksCleaner
{

    /** @var WebhookResource */
    private $resource;

    /**
     * @param WebhookResource $resource
     */
    public function __construct(WebhookResource $resource)
    {
        $this->resource = $resource;
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function execute(): void
    {
        $connection = $this->resource->getConnection();
        $selectQuery = $connection
            ->select()
            ->from(['webhooks' => $this->resource->getMainTable()])
            ->where('webhooks.is_processed = true AND webhooks.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');

        $connection->query($selectQuery->deleteFromSelect('webhooks'));
    }
}
