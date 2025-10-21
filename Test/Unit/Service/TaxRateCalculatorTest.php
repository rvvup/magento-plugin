<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Service;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Tax\Model\Calculation;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Service\TaxRateCalculator;

class TaxRateCalculatorTest extends TestCase
{

    /** @var Calculation */
    private $taxCalculationMock;

    /** @var Quote */
    private $quoteMock;

    /** @var Product */
    private $productMock;

    /** @var TaxRateCalculator */
    private $calculator;

    protected function setUp(): void
    {
        $this->taxCalculationMock = $this->getMockBuilder(Calculation::class)
            ->disableOriginalConstructor()->getMock();
        $this->taxCalculationMock->method('getRateRequest')->willReturn(new DataObject([]));

        $this->productMock = $this->getMockBuilder(Product::class)
            ->addMethods(['getTaxClassId'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->productMock->method('getTaxClassId')->willReturn(5);
        $this->quoteMock = $this->createMock(Quote::class);
        $this->calculator = new TaxRateCalculator($this->taxCalculationMock);

    }

    public function testTaxClassIdIsNull()
    {
        $this->productMock->method('getTaxClassId')->willReturn(null);

        $actual = $this->calculator->getItemTaxRate($this->quoteMock, $this->productMock);

        $this->assertNull($actual);
    }


    public function testRetrievingRateIsZeroFromTaxCalculator()
    {
        $this->taxCalculationMock->expects($this->once())->method('getRate')
            ->with($this->callback(function ($request) {
                return $request->getData('product_class_id') === 5;
            }))->willReturn(0);

        $actual = $this->calculator->getItemTaxRate($this->quoteMock, $this->productMock);

        $this->assertNull($actual);
    }


    public function testRetrievingRateFromTaxCalculator()
    {
        $this->taxCalculationMock->expects($this->once())->method('getRate')
            ->with($this->callback(function ($request) {
                return $request->getData('product_class_id') === 5;
            }))->willReturn(20.5);

        $actual = $this->calculator->getItemTaxRate($this->quoteMock, $this->productMock);

        $this->calculator->getItemTaxRate($this->quoteMock, $this->productMock); // call again to test caching

        $this->assertEquals("20.5", $actual);
    }

    public function testExceptionHandlingReturnsNull()
    {
        $this->taxCalculationMock->method('getRate')->willThrowException(new Exception("Tax calculation error"));

        $actual = $this->calculator->getItemTaxRate($this->quoteMock, $this->productMock);

        $this->assertNull($actual);
    }
}
