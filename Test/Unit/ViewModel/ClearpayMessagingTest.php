<?php declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Data;
use Magento\Tax\Model\Config as TaxConfig;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\Clearpay\Config;
use Rvvup\Payments\Model\ComplexProductTypePool;
use Rvvup\Payments\Model\ThresholdProvider;
use Rvvup\Payments\ViewModel\Clearpay;
use Magento\Checkout\Model\Session;
use Magento\Framework\Locale\Resolver;
use Magento\Store\Model\StoreManagerInterface;
use Rvvup\Payments\ViewModel\Price;

class ClearpayMessagingTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject|Config */
    private $configMock;
    /** @var ProductInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $productMock;
    /** @var Clearpay */
    private $viewModel;

    /** @var Price $priceHelperMock */
    private $priceHelperMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->configMock = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();
        $productRepositoryMock = $this->getMockBuilder(ProductRepositoryInterface::class)->getMock();
        $this->productMock = $this->getMockBuilder(ProductInterface::class)
            ->addMethods(['getRvvupRestricted','getFinalPrice'])->getMockForAbstractClass();
        $thresholdProviderMock = $this->getMockBuilder(ThresholdProvider::class)->disableOriginalConstructor()
            ->onlyMethods(['get'])->getMock();
        $thresholdProviderMock->method('get')->with('CLEARPAY')
            ->willReturn(['GBP' => [
                'min' => 50.00,
                'max' => 500.00,
            ]]);
        $sessionMock = $this->getMockBuilder(Session::class)->disableOriginalConstructor()->getMock();
        $storeManagerMock = $this->getMockBuilder(StoreManagerInterface::class)->disableOriginalConstructor()->getMock();
        $currency = new DataObject(['code' => 'GBP']);
        $store = new DataObject(['current_currency' => $currency]);
        $storeManagerMock->method('getStore')->willReturn($store);
        $resolverMock = $this->getMockBuilder(Resolver::class)->disableOriginalConstructor()->getMock();
        $loggerMock = $this->getMockBuilder(LoggerInterface::class)->disableOriginalConstructor()->getMock();
        //$this->priceHelperMock = $this->getMockBuilder(Data::class)->disableOriginalConstructor()->getMock();
        $this->priceHelperMock = $this->getMockBuilder(Price::class)->disableOriginalConstructor()->getMock();
        $this->viewModel = new Clearpay(
            $this->configMock,
            $productRepositoryMock,
            $thresholdProviderMock,
            $sessionMock,
            $storeManagerMock,
            $resolverMock,
            (new ComplexProductTypePool([
                "configurable" => 'configurable',
                "grouped" => 'grouped',
                "bundle" => 'bundle',
            ])),
            $loggerMock,
            $this->priceHelperMock
        );
    }

    protected function tearDown(): void
    {
        $this->configMock = null;
        $this->viewModel = null;
    }

    /**
     * Tests:
     * - Messaging is disabled does not show messaging
     * @return void
     */
    public function testMessagingDisabled()
    {
        $this->setMessagingStatus(false);
        $this->assertFalse(
            $this->viewModel->showBySku('ABC123'),
            'Messaging is shown but is disabled'
        );
    }

    /**
     * Tests:
     * - Messaging is enabled
     * - Product price is in range
     * - Product is not restricted
     * @return void
     */
    public function testProductInRangeNotRestricted()
    {
        $this->setMessagingStatus(true);
        $this->configureProduct(false, 200.00);
        $this->assertTrue(
            $this->viewModel->showByProduct($this->productMock),
            'Messaging is not shown even though product is eligible'
        );

    }

    /**
     * Tests:
     * - Messaging is enabled
     * - Product price is in range
     * - Product is restricted
     * @return void
     */
    public function testProductInRangeIsRestricted()
    {
        $this->setMessagingStatus(true);
        $this->configureProduct(true, 200.00);
        $this->assertFalse(
            $this->viewModel->showByProduct($this->productMock),
            'Messaging is shown even though product is restricted'
        );
    }

    /**
     * Tests:
     * - Messaging is enabled
     * - Product price is out of range
     * - Product is not restricted
     * @return void
     */
    public function testProductOutOfRangeNotRestricted()
    {
        $this->setMessagingStatus(true);
        $this->configureProduct(false, 49.99);
        $this->assertFalse(
            $this->viewModel->showByProduct($this->productMock),
            'Messaging is shown even though product is restricted and price is out of price range'
        );
    }

    /**
     * Tests:
     * - Messaging is enabled
     * - Product price is out of range
     * - Product is restricted
     * @return void
     */
    public function testProductOutOfRangeIsRestricted()
    {
        $this->setMessagingStatus(true);
        $this->configureProduct(true, 999.99);
        $this->assertFalse(
            $this->viewModel->showByProduct($this->productMock),
            'Messaging is shown even though product is out of price range'
        );
    }


    private function setMessagingStatus(bool $status)
    {
        $this->configMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn($status);
    }

    private function configureProduct(bool $restricted, float $price)
    {
        $this->productMock->method('getRvvupRestricted')
            ->willReturn($restricted);

        $this->productMock->method('getFinalPrice')
            ->willReturn($price);

        $this->priceHelperMock->method('getPrice')->willReturn($price);
    }
}
