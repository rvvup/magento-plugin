<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\ResourceModel\WebhookModel;

use Rvvup\Payments\Model\ResourceModel\WebhookResource;
use Rvvup\Payments\Model\WebhookModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class WebhookCollection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'rvvup_webhook_collection';

    /**
     * Initialize collection model.
     */
    protected function _construct()
    {
        $this->_init(WebhookModel::class, WebhookResource::class);
    }
}
