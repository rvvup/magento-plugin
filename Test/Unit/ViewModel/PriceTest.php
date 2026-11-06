<?php declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\ViewModel;

use Magento\Catalog\Helper\Data;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\PriceInfoInterface;
use Magento\Tax\Model\Config as TaxConfig;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\ViewModel\Price;

class PriceTest extends TestCase
{
    public function testReturnsTheValueWhenPricesAreDisplayedIncludingTax(): void
    {
        $price = $this->buildPrice(TaxConfig::DISPLAY_TYPE_INCLUDING_TAX);

        $this->assertSame(990.0, $price->getPriceIncludingTax($this->buildProduct($this->amount(990.0, 825.0))));
    }

    public function testAddsTheTaxBackWhenPricesAreDisplayedExcludingTax(): void
    {
        $price = $this->buildPrice(TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX);

        $this->assertSame(990.0, $price->getPriceIncludingTax($this->buildProduct($this->amount(825.0, 825.0))));
    }

    public function testFallsBackToTheMinimalPriceForAnEmptyDynamicBundle(): void
    {
        $price = $this->buildPrice(TaxConfig::DISPLAY_TYPE_INCLUDING_TAX);
        $product = $this->buildProduct($this->amount(0.0, 0.0), $this->amount(61.0, 50.83), 'bundle');

        $this->assertSame(61.0, $price->getPriceIncludingTax($product));
    }

    private function buildPrice(int $displayType): Price
    {
        $taxConfig = $this->createMock(TaxConfig::class);
        $taxConfig->method('getPriceDisplayType')->willReturn($displayType);

        $taxHelper = $this->createMock(Data::class);
        // Stand-in for Magento's tax calculation: 20% VAT when asked for the price including tax.
        $taxHelper->method('getTaxPrice')->willReturnCallback(
            static function ($product, $value, $includingTax = null) {
                return $includingTax ? round($value * 1.2, 2) : $value;
            }
        );

        return new Price($taxHelper, $taxConfig);
    }

    private function amount(float $value, float $base): AmountInterface
    {
        $amount = $this->createMock(AmountInterface::class);
        $amount->method('getValue')->willReturn($value);
        $amount->method('getBaseAmount')->willReturn($base);

        return $amount;
    }

    private function buildProduct(
        AmountInterface $amount,
        ?AmountInterface $minimal = null,
        string $type = 'simple'
    ): Product {
        $finalPrice = $this->createMock(FinalPrice::class);
        $finalPrice->method('getAmount')->willReturn($amount);
        $finalPrice->method('getMinimalPrice')->willReturn($minimal ?? $amount);

        $priceInfo = $this->createMock(PriceInfoInterface::class);
        $priceInfo->method('getPrice')->willReturn($finalPrice);

        $product = $this->createMock(Product::class);
        $product->method('getPriceInfo')->willReturn($priceInfo);
        $product->method('getTypeId')->willReturn($type);
        $product->method('getFinalPrice')->willReturn(0.0);

        return $product;
    }
}
