<?php

namespace Rvvup\Payments\Model\ResourceModel\HashModel;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Rvvup\Payments\Model\HashModel;
use Rvvup\Payments\Model\ResourceModel\HashResource;

class HashCollection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'rvvup_hash_collection';

    /**
     * Initialize collection model.
     */
    protected function _construct()
    {
        $this->_init(HashModel::class, HashResource::class);
    }
}
