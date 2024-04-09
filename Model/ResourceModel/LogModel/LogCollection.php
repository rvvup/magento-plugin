<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\ResourceModel\WebhookModel;

use Rvvup\Payments\Model\ResourceModel\LogResource;
use Rvvup\Payments\Model\LogModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class LogCollection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'rvvup_log_collection';

    /**
     * Initialize collection model.
     */
    protected function _construct()
    {
        $this->_init(LogModel::class, LogResource::class);
    }
}
