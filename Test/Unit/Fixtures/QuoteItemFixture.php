<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Fixtures;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QuoteItemFixture
{
    private $testCase;
    private $sku = 'TEST-SKU-001';
    private $additionalMethods = [];

    public function __construct(TestCase $testCase)
    {
        $this->testCase = $testCase;
    }

    /**
     * Create a new QuoteFixture builder instance
     *
     * @param TestCase $testCase
     * @return self
     */
    public static function builder(TestCase $testCase): self
    {
        return new self($testCase);
    }

    /**
     * Set the sku for item
     *
     * @param string $sku
     * @return self
     */
    public function withSku(string $sku): self
    {
        $this->sku = $sku;
        return $this;
    }


    /**
     * Add a custom method behavior to the quote item mock
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
     * Build and return the Quote item mock
     *
     * @return Item|MockObject
     */
    public function build(): MockObject
    {
        $item = $this->testCase->getMockBuilder(Item::class)
            ->disableOriginalConstructor()->getMock();
        $item->method('getSku')->willReturn($this->sku);

        // Apply additional methods
        foreach ($this->additionalMethods as $methodName => $returnValue) {
            $item->method($methodName)->willReturn($returnValue);
        }

        return $item;
    }

    /**
     * Create a basic Quote item mock with common default behaviors
     *
     * @param TestCase $testCase
     * @return Quote|MockObject
     */
    public static function create(TestCase $testCase): MockObject
    {
        return self::builder($testCase)->build();
    }
}
