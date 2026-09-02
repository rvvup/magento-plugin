<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Model\Shipping;

use Magento\Quote\Api\Data\ShippingMethodInterface;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Model\Shipping\ShippingMethod;

/**
 * @covers \Rvvup\Payments\Model\Shipping\ShippingMethod
 */
class ShippingMethodTest extends TestCase
{
    private function makeMethod(
        float $price,
        string $carrier = 'flatrate',
        string $method = 'flatrate',
        ?string $carrierTitle = 'Flat Rate',
        ?string $methodTitle = 'Fixed'
    ): ShippingMethodInterface {
        $mock = $this->createMock(ShippingMethodInterface::class);
        $mock->method('getPriceInclTax')->willReturn($price);
        $mock->method('getCarrierCode')->willReturn($carrier);
        $mock->method('getMethodCode')->willReturn($method);
        $mock->method('getCarrierTitle')->willReturn($carrierTitle);
        $mock->method('getMethodTitle')->willReturn($methodTitle);
        return $mock;
    }

    // --- Amount with no discount (module not installed) ---

    public function testFullPriceReturnedWhenNoDiscountPresent(): void
    {
        $sm = new ShippingMethod($this->makeMethod(7.50), 'GBP');
        $this->assertSame('7.50', $sm->getAmount());
    }

    public function testExplicitZeroDiscountBehavesLikeNoDiscount(): void
    {
        $sm = new ShippingMethod($this->makeMethod(7.50), 'GBP', 0.0);
        $this->assertSame('7.50', $sm->getAmount());
    }

    // --- Fixed shipping with partial discount ---

    public function testPartialDiscountReducesAmount(): void
    {
        $sm = new ShippingMethod($this->makeMethod(10.00), 'GBP', 3.00);
        $this->assertSame('7.00', $sm->getAmount());
    }

    // --- Shipping fully covered by discount ---

    public function testAmountIsZeroWhenDiscountEqualsShipping(): void
    {
        $sm = new ShippingMethod($this->makeMethod(7.50), 'GBP', 7.50);
        $this->assertSame('0.00', $sm->getAmount());
    }

    public function testAmountIsFlooredAtZeroWhenDiscountExceedsShipping(): void
    {
        $sm = new ShippingMethod($this->makeMethod(7.50), 'GBP', 10.00);
        $this->assertSame('0.00', $sm->getAmount());
    }

    // --- Free shipping method (e.g. freeshipping_freeshipping or custom zero-rate) ---

    public function testFreeShippingMethodIsZeroWithoutDiscount(): void
    {
        $sm = new ShippingMethod($this->makeMethod(0.00, 'freeshipping', 'freeshipping'), 'GBP');
        $this->assertSame('0.00', $sm->getAmount());
    }

    // --- Non-amount fields are unaffected by the discount ---

    public function testIdIsUnaffectedByDiscount(): void
    {
        $noDiscount = new ShippingMethod($this->makeMethod(7.50, 'flatrate', 'flatrate'), 'GBP');
        $withDiscount = new ShippingMethod($this->makeMethod(7.50, 'flatrate', 'flatrate'), 'GBP', 7.50);
        $this->assertSame($noDiscount->getId(), $withDiscount->getId());
    }

    public function testLabelIsUnaffectedByDiscount(): void
    {
        $noDiscount = new ShippingMethod($this->makeMethod(7.50, 'flatrate', 'flatrate', 'Flat Rate', 'Fixed'), 'GBP');
        $withDiscount = new ShippingMethod($this->makeMethod(7.50, 'flatrate', 'flatrate', 'Flat Rate', 'Fixed'), 'GBP', 7.50);
        $this->assertSame($noDiscount->getLabel(), $withDiscount->getLabel());
    }

    public function testCurrencyIsUnaffectedByDiscount(): void
    {
        $noDiscount = new ShippingMethod($this->makeMethod(7.50), 'GBP');
        $withDiscount = new ShippingMethod($this->makeMethod(7.50), 'GBP', 7.50);
        $this->assertSame($noDiscount->getCurrency(), $withDiscount->getCurrency());
    }

    public function testIdIsCarrierCodeUnderscoreMethodCode(): void
    {
        $sm = new ShippingMethod($this->makeMethod(7.50, 'humanware_customups', '1'), 'GBP');
        $this->assertSame('humanware_customups_1', $sm->getId());
    }
}
