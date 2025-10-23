<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Fixtures;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderAddressFixture
{
    private $testCase;
    private $additionalMethods = [];

    public function __construct(TestCase $testCase)
    {
        $this->testCase = $testCase;
    }

    /**
     * Create a new OrderAddressFixture builder instance
     *
     * @param TestCase $testCase
     * @return self
     */
    public static function builder(TestCase $testCase): self
    {
        return new self($testCase);
    }


    /**
     * Add a custom method behavior to the order mock
     *
     * @param string $methodName
     * @param mixed $returnValue
     * @return self
     */
    public function withMethod(string $methodName, $returnValue): self
    {
        $this->additionalMethods[$methodName] = $returnValue;
        return $this;
    }

    /**
     * Build and return the order address mock
     *
     * @return Address|MockObject
     */
    public function build(): MockObject
    {
        $mock = $this->testCase->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->getMock();

        foreach ($this->additionalMethods as $methodName => $returnValue) {
            $mock->method($methodName)->willReturn($returnValue);
        }

        return $mock;
    }

    /**
     * Create a basic order address mock with common default behaviors
     *
     * @param TestCase $testCase
     * @return Address|MockObject
     */
    public static function create(TestCase $testCase): MockObject
    {
        return self::builder($testCase)->build();
    }
}
