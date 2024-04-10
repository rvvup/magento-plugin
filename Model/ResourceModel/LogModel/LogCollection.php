<?php

namespace Rvvup\Payments\Model\ResourceModel\LogModel;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Rvvup\Payments\Model\LogModel;
use Rvvup\Payments\Model\ResourceModel\LogResource;

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
