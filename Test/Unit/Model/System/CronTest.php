<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Model\System;

use Magento\Cron\Model\ResourceModel\Schedule\Collection;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Model\System\Message\Cron;

class CronTest extends TestCase
{
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var Cron $cron
     */
    private $cron;

    protected function setUp(): void
    {
        $this->collectionFactory = $this->getMockBuilder(CollectionFactory::class)->disableOriginalConstructor()->getMock();
        $this->configureCollection();

        $this->cron = new Cron(
            $this->collectionFactory
        );
    }

    private function configureCollection($array = []) {
        $collection = $this->getMockBuilder(Collection::class)->disableOriginalConstructor()->getMock();
        $collection->method('getAllIds')->willReturn($array);
        $this->collectionFactory->method('create')->willReturn($collection);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->cron = null;
    }

    /**
     * @return void
     */
    public function testDisplayWithCronEnabled()
    {
        $this->configureCollection([1]);
        $this->assertEquals(
            true,
            $this->cron->isDisplayed()
        );
    }

    /**
     * @return void
     */
    public function testDisplayWithCronDisabled()
    {
        $this->configureCollection();
        $this->assertEquals(
            true,
            $this->cron->isDisplayed()
        );
    }
}
