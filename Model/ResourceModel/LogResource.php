<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\ResourceModel;

use Rvvup\Payments\Api\Data\LogInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class LogResource extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'rvvup_log_resource_model';

    /**
     * Initialize resource model.
     */
    protected function _construct()
    {
        $this->_init('rvvup_log', LogInterface::LOG_ID);
        $this->_useIsObjectNew = true;
    }
}
