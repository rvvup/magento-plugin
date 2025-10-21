<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Fixtures;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QuoteFixture
{
    private $testCase;
    private $items = [];
    private $storeId = 1;
    private $quoteId = 123;
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
     * Set the items for the quote
     *
     * @param array $items
     * @return self
     */
    public function withItems(array $items): self
    {
        $this->items = $items;
        return $this;
    }

    /**
     * Set the store ID for the quote
     *
     * @param int $storeId
     * @return self
     */
    public function withStoreId(int $storeId): self
    {
        $this->storeId = $storeId;
        return $this;
    }

    /**
     * Set the quote ID
     *
     * @param int $quoteId
     * @return self
     */
    public function withQuoteId(int $quoteId): self
    {
        $this->quoteId = $quoteId;
        return $this;
    }

    /**
     * Add a custom method behavior to the quote mock
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
     * Build and return the Quote mock
     *
     * @return Quote|MockObject
     */
    public function build(): MockObject
    {
        $quoteMock = $this->testCase->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->getMock();

        $quoteMock->method('getStoreId')->willReturn($this->storeId);
        $quoteMock->method('getItems')->willReturn($this->items);
        $quoteMock->method('getId')->willReturn($this->quoteId);

        // Apply additional methods
        foreach ($this->additionalMethods as $methodName => $returnValue) {
            $quoteMock->method($methodName)->willReturn($returnValue);
        }

        return $quoteMock;
    }

    /**
     * Create a basic Quote mock with common default behaviors (legacy method for backward compatibility)
     *
     * @param TestCase $testCase
     * @return Quote|MockObject
     */
    public static function create(TestCase $testCase): MockObject
    {
        return self::builder($testCase)->build();
    }
}
