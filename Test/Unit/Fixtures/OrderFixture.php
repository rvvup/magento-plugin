<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Fixtures;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderFixture
{
    private $testCase;
    private $additionalMethods = [];

    public function __construct(TestCase $testCase)
    {
        $this->testCase = $testCase;
    }

    /**
     * Create a new OrderFixture builder instance
     *
     * @param TestCase $testCase
     * @return self
     */
    public static function builder(TestCase $testCase): self
    {
        return new self($testCase);
    }


    /**
     * Set the order ID
     *
     * @param int $orderId
     * @return self
     */
    public function withId(int $orderId): self
    {
        return $this->withMethod('getId', $orderId);
    }

    /**
     * Set the customer first name
     *
     * @param string $customerFirstname
     * @return self
     */
    public function withCustomerFirstname(string $customerFirstname): self
    {
        return $this->withMethod('getCustomerFirstname', $customerFirstname);
    }

    /**
     * Set the customer last name
     *
     * @param string $customerLastname
     * @return self
     */
    public function withCustomerLastname(string $customerLastname): self
    {

        return $this->withMethod('getCustomerLastname', $customerLastname);
    }

    /**
     * Set the customer email
     *
     * @param string $value
     * @return self
     */
    public function withCustomerEmail(string $value): self
    {

        return $this->withMethod('getCustomerEmail', $value);
    }

    /**
     * Set the customer last name
     *
     * @param Address $address
     * @return self
     */
    public function withBillingAddress(Order\Address $address): self
    {

        return $this->withMethod('getBillingAddress', $address);
    }

    /**
     * Set the customer last name
     *
     * @param Address $address
     * @return self
     */
    public function withShippingAddress(Order\Address $address): self
    {

        return $this->withMethod('getShippingAddress', $address);
    }

    /**
     * @param OrderPaymentInterface|MockObject|null $orderPayment
     * @return $this
     */
    public function withOrderPayment($orderPayment): self
    {
        return $this->withMethod('getPayment', $orderPayment);
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
     * Build and return the order mock
     *
     * @return OrderInterface|MockObject
     */
    public function build(): MockObject
    {
        $mock = $this->testCase->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->additionalMethods['getId'] = $this->additionalMethods['getId'] ?? 1;
        $this->additionalMethods['getCustomerFirstname'] = $this->additionalMethods['getCustomerFirstname'] ?? 'John';
        $this->additionalMethods['getCustomerLastname'] = $this->additionalMethods['getCustomerLastname'] ?? 'Doe';
        $this->additionalMethods['getPayment'] = $this->additionalMethods['getPayment'] ?? OrderPaymentFixture::create($this->testCase);

        foreach ($this->additionalMethods as $methodName => $returnValue) {
            $mock->method($methodName)->willReturn($returnValue);
        }

        return $mock;
    }

    /**
     * Create a basic order mock with common default behaviors
     *
     * @param TestCase $testCase
     * @return OrderInterface|MockObject
     */
    public static function create(TestCase $testCase): MockObject
    {
        return self::builder($testCase)->build();
    }
}
