<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\System\Message;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Notification\MessageInterface;
use Magento\Cron\Model\ResourceModel\Schedule\Collection;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory;

class Queue implements MessageInterface
{

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var DeploymentConfig
     */
    private DeploymentConfig $config;

    /**
     * @param CollectionFactory $collectionFactory
     * @param DeploymentConfig $config
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        DeploymentConfig $config
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->config = $config;
    }

    /**
     * Message identity
     */
    private const MESSAGE_IDENTITY = 'rvvup_missing_queues';

    public function getIdentity()
    {
        return self::MESSAGE_IDENTITY;
    }

    public function isDisplayed()
    {
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();

        $collection->addFieldToFilter('job_code', ['eq' => 'consumers_runner']);

        $rabbitMq = $this->config->get('amqp');

        return empty($collection->getAllIds()) && !$rabbitMq;
    }

    public function getText()
    {
        return "<b>WARNING</b>: Rvvup requires Magento MessageQueue to be enabled in order to handle payment events that complete asynchronously."
            . "<br>It appears that this may not be configured on your Magento instance."
            . "<br>See <a href='https://articles.rvvup.com/getting-started-with-magento-and-rvvup'>our documentation</a> for more information.";
    }

    public function getSeverity()
    {
        return self::SEVERITY_NOTICE;
    }
}
