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
    public function __construct(
        LogResource $resource
    )
    {
        $this->resource = $resource;
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function execute(): void
    {
        $this->resource->getConnection()->query('DELETE FROM ' . $this->resource->getMainTable()
            . ' WHERE is_processed = true ORDER BY entity_id ASC LIMIT 100');
    }
}
