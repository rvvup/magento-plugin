<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Rvvup\Payments\Model\ResourceModel\WebhookResource;
use Magento\Framework\Model\AbstractModel;

class WebhookModel extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'rvvup_webhook_model';

    /**
     * Initialize magento model.
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(WebhookResource::class);
    }
}
