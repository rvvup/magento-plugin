<?php declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Model\ProcessOrder;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Model\ProcessOrder\ProcessorInterface;
use Rvvup\Payments\Model\ProcessOrder\ProcessorPool;

class ProcessorPoolTest extends TestCase
{
    /** @var ProcessorInterface */
    private $processor;

    /** @var ProcessorPool */
    private $processorPool;

    protected function setUp(): void
    {
        $this->processor = $this->createMock(ProcessorInterface::class);

        $this->processorPool = new ProcessorPool(
            ['SUCCEEDED' => $this->processor],
            []
        );
    }

    public function testFindProcessorReturnsTheProcessorForAKnownStatus(): void
    {
        $this->assertSame($this->processor, $this->processorPool->findProcessor('SUCCEEDED'));
    }

    public function testFindProcessorReturnsNullForAnUnknownStatus(): void
    {
        $this->assertNull($this->processorPool->findProcessor('CREATED'));
    }

    public function testGetProcessorReturnsTheProcessorForAKnownStatus(): void
    {
        $this->assertSame($this->processor, $this->processorPool->getProcessor('SUCCEEDED'));
    }

    public function testGetProcessorStillThrowsForAnUnknownStatus(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('No OrderProcessor for status CREATED');

        $this->processorPool->getProcessor('CREATED');
    }
}
