<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Model\Payment;

use Magento\Payment\Model\InfoInterface;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Payment\VaultDataBuilder;
use Rvvup\Payments\Model\Payment\VaultPaymentSessionCreateInput;

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
        $input = new VaultPaymentSessionCreateInput();
        $payment = $this->createPaymentMock([Method::SAVE_PAYMENT_METHOD => true]);
        $payment->expects($this->once())
            ->method('setAdditionalInformation')
            ->with(Method::SAVE_TOKEN_REQUESTED, '1');

        $this->builder->build($input, $payment, 42);

        $this->assertSame('CUSTOMER', $input->getSaveTokenScope());
    }

    public function testDoesNotSetTokenScopeWhenFlagAbsent(): void
    {
        $input = new VaultPaymentSessionCreateInput();
        $payment = $this->createPaymentMock([]);
        $payment->expects($this->never())->method('setAdditionalInformation');

        $this->builder->build($input, $payment, 42);

        $this->assertNull($input->getSaveTokenScope());
    }

    public function testDoesNotSetTokenScopeForGuestCustomer(): void
    {
        $input = new VaultPaymentSessionCreateInput();
        $payment = $this->createPaymentMock([Method::SAVE_PAYMENT_METHOD => true]);
        $payment->expects($this->never())->method('setAdditionalInformation');

        $this->builder->build($input, $payment, 0);

        $this->assertNull($input->getSaveTokenScope());
    }

    public function testDoesNotSetTokenScopeWhenFlagFalse(): void
    {
        $input = new VaultPaymentSessionCreateInput();
        $payment = $this->createPaymentMock([Method::SAVE_PAYMENT_METHOD => false]);
        $payment->expects($this->never())->method('setAdditionalInformation');

        $this->builder->build($input, $payment, 42);

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
