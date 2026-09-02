<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Service\Shipping;

use Magento\Checkout\Api\Data\ShippingInformationInterfaceFactory;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Model\Shipping\ShippingMethod;
use Rvvup\Payments\Service\Shipping\ShippingMethodService;

/**
 * @covers \Rvvup\Payments\Service\Shipping\ShippingMethodService::getAvailableShippingMethods
 */
class ShippingMethodServiceTest extends TestCase
{
    /** @var ShipmentEstimationInterface|MockObject */
    private $shipmentEstimation;

    /** @var ShippingMethodService */
    private $service;

    protected function setUp(): void
    {
        $this->shipmentEstimation = $this->createMock(ShipmentEstimationInterface::class);

        $this->service = new ShippingMethodService(
            $this->shipmentEstimation,
            $this->createMock(CartRepositoryInterface::class),
            $this->createMock(ShippingInformationInterfaceFactory::class),
            $this->createMock(ShippingInformationManagementInterface::class)
        );
    }

    private function makeAddress(float $shippingDiscount = 0.0): Address|MockObject
    {
        // getShippingDiscountAmount is a Magento magic DataObject method — addMethods() makes it mockable.
        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->addMethods(['getShippingDiscountAmount'])
            ->getMock();
        $address->method('getShippingDiscountAmount')->willReturn($shippingDiscount);
        return $address;
    }

    private function makeQuote(float $shippingDiscount = 0.0, string $currency = 'GBP'): Quote|MockObject
    {
        // getQuoteCurrencyCode and setTotalsCollectedFlag are DataObject magic methods.
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['getQuoteCurrencyCode', 'setTotalsCollectedFlag'])
            ->onlyMethods(['getId', 'getShippingAddress', 'collectTotals'])
            ->getMock();
        $quote->method('getId')->willReturn(1);
        $quote->method('getShippingAddress')->willReturn($this->makeAddress($shippingDiscount));
        $quote->method('getQuoteCurrencyCode')->willReturn($currency);

        return $quote;
    }

    private function makeShippingMethod(
        float $price,
        string $carrier = 'flatrate',
        string $method = 'flatrate',
        ?string $error = null
    ): ShippingMethodInterface|MockObject {
        $mock = $this->createMock(ShippingMethodInterface::class);
        $mock->method('getPriceInclTax')->willReturn($price);
        $mock->method('getCarrierCode')->willReturn($carrier);
        $mock->method('getMethodCode')->willReturn($method);
        $mock->method('getCarrierTitle')->willReturn('Carrier');
        $mock->method('getMethodTitle')->willReturn('Method');
        $mock->method('getErrorMessage')->willReturn($error);
        return $mock;
    }

    // --- Empty results ---

    public function testReturnsEmptyArrayWhenEstimationReturnsNoMethods(): void
    {
        $this->shipmentEstimation->method('estimateByExtendedAddress')->willReturn([]);

        $result = $this->service->getAvailableShippingMethods($this->makeQuote());

        $this->assertSame([], $result);
    }

    // --- No discount module present ---

    public function testReturnsFullPriceWhenNoDiscountModulePresent(): void
    {
        $this->shipmentEstimation->method('estimateByExtendedAddress')
            ->willReturn([$this->makeShippingMethod(7.50)]);

        $result = $this->service->getAvailableShippingMethods($this->makeQuote(shippingDiscount: 0.0));

        $this->assertCount(1, $result);
        $this->assertSame('7.50', $result[0]->getAmount());
    }

    // --- Fixed shipping + discount covering full cost ---

    public function testReturnsZeroAmountWhenDiscountCoversFullShipping(): void
    {
        $this->shipmentEstimation->method('estimateByExtendedAddress')
            ->willReturn([$this->makeShippingMethod(7.50)]);

        $result = $this->service->getAvailableShippingMethods($this->makeQuote(shippingDiscount: 7.50));

        $this->assertCount(1, $result);
        $this->assertSame('0.00', $result[0]->getAmount());
    }

    // --- Fixed shipping + partial discount ---

    public function testReturnsReducedAmountForPartialDiscount(): void
    {
        $this->shipmentEstimation->method('estimateByExtendedAddress')
            ->willReturn([$this->makeShippingMethod(10.00)]);

        $result = $this->service->getAvailableShippingMethods($this->makeQuote(shippingDiscount: 3.00));

        $this->assertCount(1, $result);
        $this->assertSame('7.00', $result[0]->getAmount());
    }

    // --- Free shipping method (already zero rate) ---

    public function testFreeShippingMethodStaysZeroWithNoDiscount(): void
    {
        $this->shipmentEstimation->method('estimateByExtendedAddress')
            ->willReturn([$this->makeShippingMethod(0.00, 'freeshipping', 'freeshipping')]);

        $result = $this->service->getAvailableShippingMethods($this->makeQuote(shippingDiscount: 0.0));

        $this->assertCount(1, $result);
        $this->assertSame('0.00', $result[0]->getAmount());
    }

    // --- Methods with errors are excluded ---

    public function testMethodsWithErrorMessagesAreExcluded(): void
    {
        $this->shipmentEstimation->method('estimateByExtendedAddress')
            ->willReturn([$this->makeShippingMethod(7.50, error: 'Service unavailable')]);

        $result = $this->service->getAvailableShippingMethods($this->makeQuote());

        $this->assertSame([], $result);
    }

    public function testOnlyValidMethodsReturnedWhenMixedWithErrors(): void
    {
        $this->shipmentEstimation->method('estimateByExtendedAddress')
            ->willReturn([
                $this->makeShippingMethod(7.50, 'flatrate', 'flatrate'),
                $this->makeShippingMethod(0.00, 'broken', 'broken', 'Not available for this address'),
                $this->makeShippingMethod(12.00, 'ups', 'express'),
            ]);

        $result = $this->service->getAvailableShippingMethods($this->makeQuote());

        $this->assertCount(2, $result);
        $this->assertSame('flatrate_flatrate', $result[0]->getId());
        $this->assertSame('ups_express', $result[1]->getId());
    }

    // --- Multiple methods - discount applied to each ---

    public function testDiscountIsAppliedToAllAvailableMethods(): void
    {
        $this->shipmentEstimation->method('estimateByExtendedAddress')
            ->willReturn([
                $this->makeShippingMethod(10.00, 'flatrate', 'flatrate'),
                $this->makeShippingMethod(20.00, 'ups', 'express'),
            ]);

        $result = $this->service->getAvailableShippingMethods($this->makeQuote(shippingDiscount: 5.00));

        $this->assertCount(2, $result);
        $this->assertSame('5.00', $result[0]->getAmount());
        $this->assertSame('15.00', $result[1]->getAmount());
    }

    // --- collectTotals is called to populate discount amounts ---

    public function testCollectTotalsIsCalledAfterEstimation(): void
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['getQuoteCurrencyCode', 'setTotalsCollectedFlag'])
            ->onlyMethods(['getId', 'getShippingAddress', 'collectTotals'])
            ->getMock();
        $quote->method('getId')->willReturn(1);
        $quote->method('getShippingAddress')->willReturn($this->makeAddress(0.0));
        $quote->method('getQuoteCurrencyCode')->willReturn('GBP');
        $quote->expects($this->once())->method('collectTotals');

        $this->shipmentEstimation->method('estimateByExtendedAddress')
            ->willReturn([$this->makeShippingMethod(7.50)]);

        $this->service->getAvailableShippingMethods($quote);
    }

    // --- Return type ---

    public function testReturnsShippingMethodInstances(): void
    {
        $this->shipmentEstimation->method('estimateByExtendedAddress')
            ->willReturn([$this->makeShippingMethod(7.50)]);

        $result = $this->service->getAvailableShippingMethods($this->makeQuote());

        $this->assertInstanceOf(ShippingMethod::class, $result[0]);
    }
}
