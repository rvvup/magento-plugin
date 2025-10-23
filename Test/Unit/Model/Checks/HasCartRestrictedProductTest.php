<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Service;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductRepository;
use Magento\Payment\Model\MethodInterface;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Model\Checks\HasCartRestrictedProduct;
use Rvvup\Payments\Model\Logger;
use Rvvup\Payments\Test\Unit\Fixtures\QuoteFixture;
use Rvvup\Payments\Test\Unit\Fixtures\QuoteItemFixture;

class HasCartRestrictedProductTest extends TestCase
{

    /** @var ProductRepository */
    private $productRepositoryMock;

    /** @var MethodInterface */
    private $paymentMethodMock;

    /** @var HasCartRestrictedProduct */
    private $hasCartRestrictedProduct;

    /**
     * Data provider for payment method availability scenarios.
     * These are the only methods that are affected by restricted products in cart.
     *
     * @return array
     */
    public function paymentMethodAvailabilityProvider(): array
    {
        return [['rvvup_CLEARPAY'], ['rvvup_ZOPA_RETAIL_FINANCE']];
    }

    protected function setUp(): void
    {
        $this->productRepositoryMock = $this->getMockBuilder(ProductRepository::class)
            ->disableOriginalConstructor()->getMock();

        $this->paymentMethodMock = $this->createMock(MethodInterface::class);

        $this->hasCartRestrictedProduct = new HasCartRestrictedProduct($this->productRepositoryMock, $this->createMock(Logger::class));

    }

    public function testMethodIsAvailableWhenNotClearpayOrZRF()
    {
        $this->paymentMethodMock->method('getCode')->willReturn('rvvup_FAKE');

        $actual = $this->hasCartRestrictedProduct->isApplicable($this->paymentMethodMock, QuoteFixture::create($this));

        $this->assertTrue($actual);
    }

    /**
     * @dataProvider paymentMethodAvailabilityProvider
     */
    public function testMethodIsAvailableWhenItemsIsEmpty(string $paymentMethodCode)
    {
        $this->paymentMethodMock->method('getCode')->willReturn($paymentMethodCode);

        $actual = $this->hasCartRestrictedProduct->isApplicable($this->paymentMethodMock, QuoteFixture::builder($this)->withItems([])->build());

        $this->assertTrue($actual);
    }

    /**
     * @dataProvider paymentMethodAvailabilityProvider
     */
    public function testMethodIsAvailableWhenItemsContainsOneStandardProduct(string $paymentMethodCode)
    {
        $this->paymentMethodMock->method('getCode')->willReturn($paymentMethodCode);

        $quote = QuoteFixture::builder($this)
            ->withItems([QuoteItemFixture::builder($this)->withSku('standard-product-sku')->build()])
            ->build();

        $productMock = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()->getMock();

        $productMock->method('getData')->with('rvvup_restricted')->willReturn(false);

        $this->productRepositoryMock->method('get')
            ->with('standard-product-sku', $quote->getStoreId())
            ->willReturn($productMock);

        $actual = $this->hasCartRestrictedProduct->isApplicable($this->paymentMethodMock, $quote);

        $this->assertTrue($actual);
    }

    /**
     * @dataProvider paymentMethodAvailabilityProvider
     */
    public function testMethodIsNotAvailableWhenItemIsRestricted(string $paymentMethodCode)
    {
        $this->paymentMethodMock->method('getCode')->willReturn($paymentMethodCode);


        $quote = QuoteFixture::builder($this)
            ->withItems([QuoteItemFixture::builder($this)->withSku('standard-product-sku')->build()])
            ->build();

        $productMock = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()->getMock();

        $productMock->method('getData')->with('rvvup_restricted')->willReturn(true);

        $this->productRepositoryMock->method('get')
            ->with('standard-product-sku', $quote->getStoreId())
            ->willReturn($productMock);

        $actual = $this->hasCartRestrictedProduct->isApplicable($this->paymentMethodMock, $quote);

        $this->assertFalse($actual);
    }
}
