<?php declare(strict_types=1);

namespace Rvvup\Payments\Model\ResourceModel;

use Rvvup\Payments\Api\Data\WebhookInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * @method save(WebhookInterface $webhook)
 * @method delete(WebhookInterface $webhook)
 */
class WebhookResource extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'rvvup_webhook_resource_model';

    /**
     * Initialize resource model.
     */
    protected function _construct()
    {
        $this->_init('rvvup_webhook', WebhookInterface::WEBHOOK_ID);
        $this->_useIsObjectNew = true;
    }
}
