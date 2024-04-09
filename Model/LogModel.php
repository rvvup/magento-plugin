<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Rvvup\Payments\Model\ResourceModel\LogResource;
use Magento\Framework\Model\AbstractModel;

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
