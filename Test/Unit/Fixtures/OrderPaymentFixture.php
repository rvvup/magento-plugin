<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Fixtures;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderPaymentFixture
{
    private $testCase;
    private $method = "rvvup_TEST";

    private $additionalMethods = [];

    public function __construct(TestCase $testCase)
    {
        $this->testCase = $testCase;
    }

    /**
     * Create a new OrderPaymentFixture builder instance
     *
     * @param TestCase $testCase
     * @return self
     */
    public static function builder(TestCase $testCase): self
    {
        return new self($testCase);
    }

    /**
     * Add a custom method behavior to the order payment mock
     *
     * @param string $paymentMethod
     * @return self
     */
    public function withPaymentMethod(string $paymentMethod): self
    {
        $this->method = $paymentMethod;
        return $this;
    }

    public function withAdditionalMethod(string $methodName, $returnValue): self
    {
        $this->additionalMethods[$methodName] = $returnValue;
        return $this;
    }

    /**
     * Build and return the order mock
     *
     * @return OrderInterface|MockObject
     */
    public function build(): MockObject
    {
        $mock = $this->testCase->getMockBuilder(OrderPaymentInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getMethod')->willReturn($this->method);

        // Apply additional methods
        foreach ($this->additionalMethods as $methodName => $returnValue) {
            $mock->method($methodName)->willReturn($returnValue);
        }

        return $mock;
    }

    /**
     * Create a basic order payment mock with common default behaviors
     *
     * @param TestCase $testCase
     * @return OrderPaymentInterface|MockObject
     */
    public static function create(TestCase $testCase): MockObject
    {
        return self::builder($testCase)->build();
    }
}
