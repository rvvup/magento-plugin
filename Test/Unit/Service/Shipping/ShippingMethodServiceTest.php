<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Service\Shipping;

use Magento\Quote\Api\Data\ShippingMethodInterface;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Model\Shipping\ShippingMethod;

/**
 * Verifies the shipping discount is passed through to ShippingMethod correctly,
 * matching the call pattern in ShippingMethodService::getAvailableShippingMethods after the fix.
 * ShippingMethodService itself depends on Checkout module classes not available in this environment.
 */
class ShippingMethodServiceTest extends TestCase
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

    private function buildShippingMethod(float $priceInclTax, float $discount): ShippingMethod
    {
        return new ShippingMethod($this->makeShippingMethodMock($priceInclTax), 'GBP', $discount);
    }

    public function testAmountWithNoDiscount(): void
    {
        $this->assertSame('10.00', $this->buildShippingMethod(10.00, 0.00)->getAmount());
    }

    public function testAmountReflectsFreeShippingDiscount(): void
    {
        // estimateByExtendedAddress returns GBP10 pre-discount,
        // but a free shipping promo means Apple Pay sheet should show GBP0.
        $this->assertSame('0.00', $this->buildShippingMethod(10.00, 10.00)->getAmount(),
            'Apple Pay sheet should show post-discount shipping amount, not pre-discount');
    }

    public function testAmountWithPartialDiscount(): void
    {
        $this->assertSame('7.00', $this->buildShippingMethod(10.00, 3.00)->getAmount());
    }
}
