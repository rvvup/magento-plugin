<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Model\Payment;

use Magento\Payment\Model\InfoInterface;
use PHPUnit\Framework\TestCase;
use Rvvup\Api\Model\PaymentSessionCreateInput;
use Rvvup\Api\Model\SavedTokenScope;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Payment\VaultDataBuilder;

/**
 * @covers \Rvvup\Payments\Model\Payment\VaultDataBuilder
 */
class VaultDataBuilderTest extends TestCase
{
    private VaultDataBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new VaultDataBuilder();
    }

    public function testSetsTokenScopeWhenFlagSetAndCustomerLoggedIn(): void
    {
        $input = new PaymentSessionCreateInput();
        $payment = $this->createPaymentMock([Method::SAVE_PAYMENT_METHOD => true]);
        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(Method::SAVE_TOKEN_REQUESTED, '1');
        $payment->expects($this->never())->method('unsAdditionalInformation');

        $this->builder->build($input, $payment, 42);

        $this->assertSame(SavedTokenScope::CUSTOMER, $input->getSaveTokenScope());
    }

    public function testDoesNotSetTokenScopeWhenFlagAbsent(): void
    {
        $input = new PaymentSessionCreateInput();
        $payment = $this->createPaymentMock([]);
        $payment->expects($this->never())->method('setAdditionalInformation');

        $this->builder->build($input, $payment, 42);

        $this->assertNull($input->getSaveTokenScope());
    }

    public function testDoesNotSetTokenScopeForGuestCustomer(): void
    {
        $input = new PaymentSessionCreateInput();
        $payment = $this->createPaymentMock([Method::SAVE_PAYMENT_METHOD => true]);
        $payment->expects($this->never())->method('setAdditionalInformation');

        $this->builder->build($input, $payment, 0);

        $this->assertNull($input->getSaveTokenScope());
    }

    public function testDoesNotSetTokenScopeWhenFlagFalse(): void
    {
        $input = new PaymentSessionCreateInput();
        $payment = $this->createPaymentMock([Method::SAVE_PAYMENT_METHOD => false]);
        $payment->expects($this->never())->method('setAdditionalInformation');

        $this->builder->build($input, $payment, 42);

        $this->assertNull($input->getSaveTokenScope());
    }

    public function testClearsStaleTokenRequestedMarkerWhenShopperOptsOut(): void
    {
        $input = new PaymentSessionCreateInput();
        $payment = $this->createPaymentMock([
            Method::SAVE_PAYMENT_METHOD => false,
            Method::SAVE_TOKEN_REQUESTED => '1',
        ]);
        $payment->expects($this->once())
            ->method('unsAdditionalInformation')
            ->with(Method::SAVE_TOKEN_REQUESTED);

        $this->builder->build($input, $payment, 42);

        $this->assertNull($input->getSaveTokenScope());
    }

    public function testClearsStaleTokenRequestedMarkerForGuestCustomer(): void
    {
        $input = new PaymentSessionCreateInput();
        $payment = $this->createPaymentMock([
            Method::SAVE_PAYMENT_METHOD => true,
            Method::SAVE_TOKEN_REQUESTED => '1',
        ]);
        $payment->expects($this->once())
            ->method('unsAdditionalInformation')
            ->with(Method::SAVE_TOKEN_REQUESTED);

        $this->builder->build($input, $payment, 0);

        $this->assertNull($input->getSaveTokenScope());
    }

    private function createPaymentMock(array $additionalInfo): InfoInterface
    {
        $mock = $this->createMock(InfoInterface::class);
        $mock->method('getAdditionalInformation')
            ->willReturnCallback(function (string $key) use ($additionalInfo) {
                return $additionalInfo[$key] ?? null;
            });
        return $mock;
    }
}
