<?php

namespace Rvvup\Payments\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Rvvup\Payments\Api\Data\HashInterface;

class HashResource extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'rvvup_hash_resource_model';

    /**
     * Initialize resource model.
     */
    protected function _construct()
    {
        $this->_init('rvvup_hash', HashInterface::HASH_ID);
        $this->_useIsObjectNew = true;
    }
}
