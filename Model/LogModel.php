<?php

namespace Rvvup\Payments\Model;

use Magento\Framework\Model\AbstractModel;
use Rvvup\Payments\Model\ResourceModel\LogResource;

class LogModel extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'rvvup_log_model';

    /**
     * Initialize magento model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(LogResource::class);
    }
}
