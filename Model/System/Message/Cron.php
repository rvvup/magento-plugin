<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\System\Message;

use Magento\Cron\Model\ResourceModel\Schedule\Collection;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory;
use Magento\Framework\Notification\MessageInterface;

class Cron implements MessageInterface
{

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        CollectionFactory $collectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Message identity
     */
    private const MESSAGE_IDENTITY = 'rvvup_missing_crons';

    public function getIdentity()
    {
        return self::MESSAGE_IDENTITY;
    }

    public function isDisplayed()
    {
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();

        return empty($collection->getAllIds());
    }

    public function getText()
    {
        $warning = "<b>WARNING</b>: ";
        $message = $warning . 'Rvvup requires Magento cron jobs to be enabled in order to handle payment';
        $message .= " events that complete asynchronously.";
        $message .= "<br>It appears that cron jobs may not be configured on your Magento instance.";
        $documentationLink = "https://articles.rvvup.com/getting-started-with-magento-and-rvvup";
        $documentation = "<a href='$documentationLink'>our documentation</a>";
        $message .= "<br>See " . $documentation . " for more information.";

        return $message;
    }

    public function getSeverity()
    {
        return self::SEVERITY_NOTICE;
    }
}
