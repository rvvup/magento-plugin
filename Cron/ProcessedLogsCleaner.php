<?php
declare(strict_types=1);

namespace Rvvup\Payments\Cron;

use Magento\Framework\Exception\LocalizedException;
use Rvvup\Payments\Model\ResourceModel\LogResource;

class ProcessedLogsCleaner
{

    /** @var LogResource */
    private $resource;

    /**
     * @param LogResource $resource
     */
    public function __construct(LogResource $resource)
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
            ->from(['logs' => $this->resource->getMainTable()])
            ->where('logs.is_processed = true');

        $connection->query($selectQuery->deleteFromSelect('logs'));
    }
}
