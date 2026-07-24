<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Model\Shipping;

use Magento\Quote\Api\Data\ShippingMethodInterface;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Model\Shipping\ShippingMethod;

class ShippingMethodTest extends TestCase
{
    private function makeShippingMethodMock(float $priceInclTax): ShippingMethodInterface
    {
        $mock = $this->createMock(ShippingMethodInterface::class);
        $mock->method('getCarrierCode')->willReturn('flatrate');
        $mock->method('getMethodCode')->willReturn('flatrate');
        $mock->method('getCarrierTitle')->willReturn('Flat Rate');
        $mock->method('getMethodTitle')->willReturn('Fixed');
        $mock->method('getPriceInclTax')->willReturn($priceInclTax);
        return $mock;
    }

    public function testAmountWithNoShippingDiscount(): void
    {
        $shippingMethod = new ShippingMethod(
            $this->makeShippingMethodMock(10.00),
            'GBP',
            0.00
        );

        $this->assertSame('10.00', $shippingMethod->getAmount());
    }

    public function testAmountIsReducedByShippingDiscount(): void
    {
        // Shipping costs £10, free shipping promo applied - net should be £0
        $shippingMethod = new ShippingMethod(
            $this->makeShippingMethodMock(10.00),
            'GBP',
            10.00
        );

        $this->assertSame('0.00', $shippingMethod->getAmount());
    }

    public function testAmountDoesNotGoBelowZero(): void
    {
        // Discount larger than shipping (edge case)
        $shippingMethod = new ShippingMethod(
            $this->makeShippingMethodMock(5.00),
            'GBP',
            10.00
        );

        $this->assertSame('0.00', $shippingMethod->getAmount());
    }

    public function testPartialShippingDiscount(): void
    {
        // £10 shipping, £3 discount -> £7 net
        $shippingMethod = new ShippingMethod(
            $this->makeShippingMethodMock(10.00),
            'GBP',
            3.00
        );

        $this->assertSame('7.00', $shippingMethod->getAmount());
    }
}
