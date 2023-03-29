<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Model\System;

use Magento\Cron\Model\ResourceModel\Schedule\Collection;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory;
use Magento\Framework\App\DeploymentConfig;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Model\System\Message\Queue;

class QueueTest extends TestCase
{
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var Queue $queue
     */
    private $queue;

    /**
     * @var DeploymentConfig $config
     */
    private $config;

    /** @inheirtDoc  */
    protected function setUp(): void
    {
        $this->collectionFactory = $this->getMockBuilder(CollectionFactory::class)->disableOriginalConstructor()->getMock();
        $this->config = $this->getMockBuilder(DeploymentConfig::class)->disableOriginalConstructor()->getMock();
        $this->configure(false);

        $this->queue = new Queue(
            $this->collectionFactory,
            $this->config
        );
    }

    /** @inheirtDoc  */
    private function configure($amqp, array $ids = []) {
        $collection = $this->getMockBuilder(Collection::class)->disableOriginalConstructor()->getMock();
        $collection->method('getAllIds')->willReturn($ids);
        $this->config->method('get')->willReturn($amqp);
        $this->collectionFactory->method('create')->willReturn($collection);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->queue = null;
    }

    /**
     * @return void
     */
    public function testDisplayWithAmqpEnabled()
    {
        $this->configure(true);
        $this->assertEquals(
            true,
            $this->queue->isDisplayed()
        );
    }

    /**
     * @return void
     */
    public function testDisplayWithAmqpDisabled()
    {
        $this->configure(false);
        $this->assertEquals(
            true,
            $this->queue->isDisplayed()
        );
    }


    /**
     * @return void
     */
    public function testDisplayWithCronEnabled()
    {
        $this->configure(false, [1]);
        $this->assertEquals(
            true,
            $this->queue->isDisplayed()
        );
    }
}

