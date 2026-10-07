<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Model\Payment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rvvup\Api\Model\PaymentSessionCreateInput;
use Rvvup\ApiException;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Payment\SavedTokenDataBuilder;

/**
 * @covers \Rvvup\Payments\Model\Payment\SavedTokenDataBuilder
 */
class SavedTokenDataBuilderTest extends TestCase
{
    private const HASH = 'hash-of-customer-42';

    /** @var PaymentTokenManagementInterface|MockObject */
    private $tokenManagement;

    /** @var SavedTokenDataBuilder */
    private $builder;

    protected function setUp(): void
    {
        $this->tokenManagement = $this->createMock(PaymentTokenManagementInterface::class);
        $this->builder = new SavedTokenDataBuilder($this->tokenManagement);
    }

    public function testDoesNothingWithoutPublicHash(): void
    {
        $input = new PaymentSessionCreateInput();
        $payment = $this->createPaymentMock([]);
        $payment->expects($this->never())->method('unsAdditionalInformation');
        $this->tokenManagement->expects($this->never())->method('getByPublicHash');

        $this->assertFalse($this->builder->build($input, $payment, 42, 'checkout', '000000001'));
        $this->assertNull($input->getSavedTokenId());
        $this->assertNull($input->getSessionKey());
    }

    public function testSetsSavedTokenIdAndSessionKeyForOwnedToken(): void
    {
        $input = new PaymentSessionCreateInput();
        $payment = $this->createPaymentMock([Method::PUBLIC_HASH => self::HASH]);
        $this->tokenManagement->expects($this->once())
            ->method('getByPublicHash')
            ->with(self::HASH, 42)
            ->willReturn($this->createToken());

        $this->assertTrue($this->builder->build($input, $payment, 42, 'checkout', '000000001'));

        $this->assertSame('rvvup-token-id', $input->getSavedTokenId());
        $this->assertSame('checkout.000000001.saved', $input->getSessionKey());
    }

    public function testDoesNotRequestSavingANewToken(): void
    {
        $input = new PaymentSessionCreateInput();
        $payment = $this->createPaymentMock([
            Method::PUBLIC_HASH => self::HASH,
            Method::SAVE_PAYMENT_METHOD => true,
            Method::SAVE_TOKEN_REQUESTED => '1',
        ]);
        $payment->expects($this->once())->method('unsAdditionalInformation')->with(Method::SAVE_TOKEN_REQUESTED);
        $payment->expects($this->never())->method('setAdditionalInformation');
        $this->tokenManagement->method('getByPublicHash')->willReturn($this->createToken());

        $this->builder->build($input, $payment, 42, 'checkout', '000000001');

        $this->assertNull($input->getSaveTokenScope());
    }

    public function testSessionKeyIsLimitedTo64Characters(): void
    {
        $input = new PaymentSessionCreateInput();
        $payment = $this->createPaymentMock([Method::PUBLIC_HASH => self::HASH]);
        $this->tokenManagement->method('getByPublicHash')->willReturn($this->createToken());

        $this->builder->build($input, $payment, 42, str_repeat('c', 80), '000000001');

        $this->assertSame(64, strlen($input->getSessionKey()));
    }

    public function testRejectsTokenOfAnotherCustomer(): void
    {
        $payment = $this->createPaymentMock([Method::PUBLIC_HASH => self::HASH]);
        // The lookup is scoped to the customer, so another customer's hash finds nothing.
        $this->tokenManagement->expects($this->once())
            ->method('getByPublicHash')
            ->with(self::HASH, 7)
            ->willReturn(null);

        $input = new PaymentSessionCreateInput();
        $this->expectException(LocalizedException::class);

        try {
            $this->builder->build($input, $payment, 7, 'checkout', '000000001');
        } finally {
            $this->assertNull($input->getSavedTokenId());
        }
    }

    public function testRejectsGuestWithoutLookingUpTheToken(): void
    {
        $payment = $this->createPaymentMock([Method::PUBLIC_HASH => self::HASH]);
        // For customer 0 the Vault would match tokens that belong to no customer.
        $this->tokenManagement->expects($this->never())->method('getByPublicHash');

        $this->expectException(LocalizedException::class);

        $this->builder->build(new PaymentSessionCreateInput(), $payment, 0, 'checkout', '000000001');
    }

    /**
     * @dataProvider unusableTokenProvider
     */
    public function testRejectsUnusableToken(array $overrides): void
    {
        $payment = $this->createPaymentMock([Method::PUBLIC_HASH => self::HASH]);
        $this->tokenManagement->method('getByPublicHash')->willReturn($this->createToken($overrides));

        $input = new PaymentSessionCreateInput();
        $this->expectException(LocalizedException::class);

        try {
            $this->builder->build($input, $payment, 42, 'checkout', '000000001');
        } finally {
            $this->assertNull($input->getSavedTokenId());
        }
    }

    public function unusableTokenProvider(): array
    {
        return [
            'inactive' => [['active' => false]],
            'other provider' => [['method' => 'braintree']],
            'no gateway token' => [['gateway' => '']],
            'expired' => [['expiresAt' => '2000-01-01 00:00:00']],
        ];
    }

    public function testAcceptsTokenThatExpiresInTheFuture(): void
    {
        $payment = $this->createPaymentMock([Method::PUBLIC_HASH => self::HASH]);
        $this->tokenManagement->method('getByPublicHash')
            ->willReturn($this->createToken(['expiresAt' => gmdate('Y-m-d H:i:s', time() + 86400)]));

        $this->assertTrue($this->builder->build(new PaymentSessionCreateInput(), $payment, 42, 'checkout', '1'));
    }

    /**
     * @dataProvider rejectionProvider
     */
    public function testRecognisesBackendRejectionOfTheToken(int $code, string $message, $body, bool $expected): void
    {
        $e = new ApiException($message, $code, [], $body);

        $this->assertSame($expected, $this->builder->isTokenRejection($e));
    }

    public function rejectionProvider(): array
    {
        return [
            'not found in message' => [400, 'Saved token not found', null, true],
            'revoked in message' => [400, 'Saved token has been revoked', null, true],
            'expired in message' => [400, 'Saved token is expired', null, true],
            'in response body' => [400, 'Bad Request', '{"message":"Saved token has been revoked"}', true],
            'different case' => [400, 'SAVED TOKEN NOT FOUND', null, true],
            'other 400' => [400, 'Invalid session key', '{"message":"Invalid total"}', false],
            'other status' => [500, 'Saved token not found', null, false],
            'unauthorised' => [401, 'Saved token not found', null, false],
        ];
    }

    private function createToken(array $overrides = []): PaymentTokenInterface
    {
        $values = $overrides + [
            'active' => true,
            'method' => 'rvvup_CARD',
            'gateway' => 'rvvup-token-id',
            'expiresAt' => null,
        ];
        $token = $this->createMock(PaymentTokenInterface::class);
        $token->method('getIsActive')->willReturn($values['active']);
        $token->method('getPaymentMethodCode')->willReturn($values['method']);
        $token->method('getGatewayToken')->willReturn($values['gateway']);
        $token->method('getExpiresAt')->willReturn($values['expiresAt']);
        return $token;
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
