<?php declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\Address;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\ConfigProvider;
use Rvvup\Payments\Model\SdkProxy;

class ConfigProviderTest extends TestCase
{
    private const DEFAULT_COMPONENT = 'Rvvup_Payments/js/view/payment/method-renderer/rvvup-method';
    private const CARD_INLINE_COMPONENT = 'Rvvup_Payments/js/view/payment/method-renderer/card';

    /** @var ConfigInterface|MockObject */
    private $configMock;

    /** @var SdkProxy|MockObject */
    private $sdkProxyMock;

    /** @var CheckoutSession|MockObject */
    private $checkoutSessionMock;

    /** @var CustomerSession|MockObject */
    private $customerSessionMock;

    /** @var Quote|MockObject */
    private $quoteMock;

    /** @var ConfigProvider */
    private $configProvider;

    protected function setUp(): void
    {
        $this->configMock = $this->createMock(ConfigInterface::class);
        $this->configMock->method('isActive')->willReturn(true);

        $this->sdkProxyMock = $this->createMock(SdkProxy::class);

        $this->quoteMock = $this->createMock(Quote::class);
        $this->quoteMock->method('getPayment')->willReturn(null);

        $this->checkoutSessionMock = $this->createMock(CheckoutSession::class);
        $this->checkoutSessionMock->method('getQuote')->willReturn($this->quoteMock);

        $this->customerSessionMock = $this->createMock(CustomerSession::class);
        $this->customerSessionMock->method('isLoggedIn')->willReturn(false);

        $addressMetadataMock = $this->createMock(AddressMetadataInterface::class);
        $customerRepositoryMock = $this->createMock(CustomerRepositoryInterface::class);
        $addressFactoryMock = $this->createMock(AddressInterfaceFactory::class);
        $addressResourceModelMock = $this->createMock(Address::class);

        $this->configProvider = new ConfigProvider(
            $this->configMock,
            $this->sdkProxyMock,
            $addressMetadataMock,
            $customerRepositoryMock,
            $this->checkoutSessionMock,
            $this->customerSessionMock,
            $addressFactoryMock,
            $addressResourceModelMock
        );
    }

    public function testAssignsTheCardSdkComponentToTheCardMethodWhenTheFlowIsInline(): void
    {
        $this->sdkProxyMock->method('getMethods')->willReturn([
            $this->buildMethod('CARD', ['flow' => 'INLINE']),
        ]);

        $config = $this->configProvider->getConfig();

        $this->assertSame(
            self::CARD_INLINE_COMPONENT,
            $config['payment'][Method::PAYMENT_TITLE_PREFIX . 'CARD']['component']
        );
    }

    public function testKeepsTheDefaultRvvupMethodComponentForTheCardMethodWhenTheFlowIsNotInline(): void
    {
        $this->sdkProxyMock->method('getMethods')->willReturn([
            $this->buildMethod('CARD', ['flow' => 'HOSTED']),
        ]);

        $config = $this->configProvider->getConfig();

        $this->assertSame(
            self::DEFAULT_COMPONENT,
            $config['payment'][Method::PAYMENT_TITLE_PREFIX . 'CARD']['component']
        );
    }

    public function testKeepsTheDefaultRvvupMethodComponentForNonCardNonApplePayMethods(): void
    {
        $this->sdkProxyMock->method('getMethods')->willReturn([
            $this->buildMethod('PAYPAL'),
        ]);

        $config = $this->configProvider->getConfig();

        $this->assertSame(
            self::DEFAULT_COMPONENT,
            $config['payment'][Method::PAYMENT_TITLE_PREFIX . 'PAYPAL']['component']
        );
    }

    private function buildMethod(string $name, array $settings = []): array
    {
        return [
            'name' => $name,
            'settings' => $settings,
            'description' => 'description',
            'logoUrl' => 'logo.png',
            'summaryUrl' => null,
            'assets' => [],
        ];
    }
}
