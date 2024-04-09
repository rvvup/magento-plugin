<?php

namespace Rvvup\Payments\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Rvvup\Payments\Api\Data\LogInterface;

class LogResource extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'log_resource_model';

    /**
     * Initialize resource model.
     */
    protected function _construct()
    {
        $this->_init('rvvup_log', LogInterface::ENTITY_ID);
        $this->_useIsObjectNew = true;
    }
}
