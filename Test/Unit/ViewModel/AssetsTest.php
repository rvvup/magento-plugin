<?php declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\ViewModel;

use Magento\Directory\Model\Currency;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Api\PaymentMethodsAssetsGetInterface;
use Rvvup\Payments\Api\PaymentMethodsSettingsGetInterface;
use Rvvup\Payments\Model\Config\RvvupConfigurationInterface;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Service\ApiProvider;
use Rvvup\Payments\ViewModel\Assets;

class AssetsTest extends TestCase
{
    private const CHECKOUT_PAGE = 'checkout_index_index';
    private const CATALOG_PRODUCT_PAGE = 'catalog_product_view';
    private const CART_PAGE = 'checkout_cart_index';

    public function testReturnsShouldCreateCheckoutAsTrueForTheInlineCardMethodOnTheCheckoutPage(): void
    {
        $assets = $this->buildAssets(self::CHECKOUT_PAGE, [
            'rvvup_card' => ['flow' => 'INLINE'],
        ]);

        $this->assertTrue($assets->shouldCreateCheckout());
    }

    public function testReturnsShouldCreateCheckoutAsFalseForAHostedCardMethod(): void
    {
        $assets = $this->buildAssets(self::CHECKOUT_PAGE, [
            'rvvup_card' => ['flow' => 'HOSTED'],
        ]);

        $this->assertFalse($assets->shouldCreateCheckout());
    }

    public function testReturnsShouldCreateCheckoutAsFalseForTheInlineCardMethodOnCatalogAndCartPages(): void
    {
        $onCatalogPage = $this->buildAssets(self::CATALOG_PRODUCT_PAGE, [
            'rvvup_card' => ['flow' => 'INLINE'],
        ]);

        $onCartPage = $this->buildAssets(self::CART_PAGE, [
            'rvvup_card' => ['flow' => 'INLINE'],
        ]);

        $this->assertFalse($onCatalogPage->shouldCreateCheckout());
        $this->assertFalse($onCartPage->shouldCreateCheckout());
    }

    public function testStillReturnsShouldCreateCheckoutAsTrueForApplePayExpressOnProductAndCartPages(): void
    {
        $settings = [
            'rvvup_apple_pay' => ['checkout' => ['express' => ['enabled' => true]]],
        ];

        $onCatalogPage = $this->buildAssets(self::CATALOG_PRODUCT_PAGE, $settings);
        $onCartPage = $this->buildAssets(self::CART_PAGE, $settings);

        $this->assertTrue($onCatalogPage->shouldCreateCheckout());
        $this->assertTrue($onCartPage->shouldCreateCheckout());
    }

    public function testStillReturnsShouldCreateCheckoutAsTrueForApplePayInline(): void
    {
        $assets = $this->buildAssets(self::CATALOG_PRODUCT_PAGE, [
            'rvvup_apple_pay' => ['applePayFlow' => 'INLINE'],
        ]);

        $this->assertTrue($assets->shouldCreateCheckout());
    }

    public function testReturnsShouldCreateCheckoutAsFalseWhenNoInlineOrExpressMethodIsAvailable(): void
    {
        $assets = $this->buildAssets(self::CHECKOUT_PAGE, []);

        $this->assertFalse($assets->shouldCreateCheckout());
    }

    public function testItReturnsNullForTheCoreSdkUrlWhenTheJwtIsNotConfigured(): void
    {
        $assets = $this->buildAssets(self::CHECKOUT_PAGE, []);

        $this->assertNull($assets->getCoreSdkUrl());
    }

    public function testItReturnsNullForThePublishableKeyWhenTheJwtIsNotConfigured(): void
    {
        $assets = $this->buildAssets(self::CHECKOUT_PAGE, []);

        $this->assertNull($assets->getPublishableKey());
    }

    public function testItNeverExposesCardScriptAssetsToThePage(): void
    {
        $assets = $this->buildAssets(self::CHECKOUT_PAGE, [], [
            'rvvup_card' => [
                'card' => ['assetType' => 'script', 'url' => 'https://cdn.eu.trustpayments.com/js/latest/st.js'],
            ],
            'rvvup_paypal' => [
                'paypal' => ['assetType' => 'script', 'url' => 'https://www.paypal.com/sdk/js'],
            ],
        ]);

        $scripts = $assets->getPaymentMethodsScriptAssets();

        $this->assertArrayNotHasKey('rvvup_card', $scripts);
        $this->assertArrayHasKey('rvvup_paypal', $scripts);
    }

    public function testItCreatesTheCheckoutForTheInlineCardMethodOnThirdPartyCheckoutPages(): void
    {
        $assets = $this->buildAssets(
            'firecheckout_index_index',
            ['rvvup_card' => ['flow' => 'INLINE']],
            [],
            ['checkout_index_index', 'firecheckout_index_index']
        );

        $this->assertTrue($assets->shouldCreateCheckout());
    }

    public function testItDoesNotCreateTheCheckoutOnCheckoutPagesThatAreNotConfigured(): void
    {
        $assets = $this->buildAssets(
            'onestepcheckout_index_index',
            ['rvvup_card' => ['flow' => 'INLINE']],
            [],
            ['checkout_index_index']
        );

        $this->assertFalse($assets->shouldCreateCheckout());
    }

    private function buildAssets(
        string $fullActionName,
        array $settings,
        array $methodAssets = [],
        array $checkoutPageFullActionNames = ['checkout_index_index']
    ): Assets {
        $serializerMock = $this->createMock(SerializerInterface::class);

        $configMock = $this->createMock(ConfigInterface::class);
        $configMock->method('isActive')->willReturn(true);

        $paymentMethodsAssetsGetMock = $this->createMock(PaymentMethodsAssetsGetInterface::class);
        $paymentMethodsAssetsGetMock->method('execute')->willReturn($methodAssets);

        $paymentMethodsSettingsGetMock = $this->createMock(PaymentMethodsSettingsGetInterface::class);
        $paymentMethodsSettingsGetMock->method('execute')->willReturn($settings);

        $currencyMock = $this->createMock(Currency::class);
        $currencyMock->method('getCode')->willReturn('GBP');

        $storeMock = $this->createMock(Store::class);
        $storeMock->method('getId')->willReturn(1);
        $storeMock->method('getCurrentCurrency')->willReturn($currencyMock);

        $storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $storeManagerMock->method('getStore')->willReturn($storeMock);

        $loggerMock = $this->createMock(LoggerInterface::class);
        $apiProviderMock = $this->createMock(ApiProvider::class);
        $rvvupConfigurationMock = $this->createMock(RvvupConfigurationInterface::class);

        $requestMock = $this->createMock(Http::class);
        $requestMock->method('getFullActionName')->willReturn($fullActionName);

        return new Assets(
            $serializerMock,
            $configMock,
            $paymentMethodsAssetsGetMock,
            $paymentMethodsSettingsGetMock,
            $storeManagerMock,
            $loggerMock,
            $apiProviderMock,
            $rvvupConfigurationMock,
            $requestMock,
            $checkoutPageFullActionNames
        );
    }
}
