<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Service\Card;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rvvup\Api\Model\PaymentMethodTokenDto;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Service\ApiProvider;
use Rvvup\Payments\Service\Card\VaultDetailsHandler;
use Rvvup\Sdk\Rest\PaymentSessions;
use Rvvup\Sdk\Rest\RvvupClient;

/**
 * @covers \Rvvup\Payments\Service\Card\VaultDetailsHandler
 */
class VaultDetailsHandlerTest extends TestCase
{
    private const SESSION_ID = 'PS01TEST';

    /** @var PaymentSessions|MockObject */
    private $paymentSessions;

    /** @var PaymentTokenManagementInterface|MockObject */
    private $tokenManagement;

    /** @var PaymentTokenFactoryInterface|MockObject */
    private $tokenFactory;

    /** @var LoggerInterface|MockObject */
    private $logger;

    /** @var VaultDetailsHandler */
    private $handler;

    protected function setUp(): void
    {
        $this->paymentSessions = $this->createMock(PaymentSessions::class);
        $client = $this->createMock(RvvupClient::class);
        $client->method('paymentSessions')->willReturn($this->paymentSessions);
        $apiProvider = $this->createMock(ApiProvider::class);
        $apiProvider->method('getSdk')->with('1')->willReturn($client);

        $this->tokenManagement = $this->createMock(PaymentTokenManagementInterface::class);
        $this->tokenFactory = $this->createMock(PaymentTokenFactoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new VaultDetailsHandler(
            $apiProvider,
            $this->tokenFactory,
            $this->tokenManagement,
            $this->logger
        );
    }

    public function testSavesTheTokenWithMappedDetails(): void
    {
        $order = $this->anOrder(42, '1');
        $this->paymentSessions->method('getSavedToken')->with(self::SESSION_ID)->willReturn($this->aToken());

        $token = $this->createMock(PaymentTokenInterface::class);
        $this->tokenFactory->method('create')->with('card')->willReturn($token);
        $token->expects($this->once())->method('setGatewayToken')->with('tok_rvvup');
        $token->expects($this->once())->method('setPaymentMethodCode')->with('rvvup_CARD');
        $token->expects($this->once())->method('setCustomerId')->with(42);
        $token->expects($this->once())->method('setExpiresAt')->with('2035-01-01 00:00:00');
        $token->expects($this->once())->method('setTokenDetails')->with(
            json_encode(['type' => 'VI', 'maskedCC' => '1111', 'expirationDate' => '12/2034'])
        );

        $this->tokenManagement->expects($this->once())
            ->method('saveTokenWithPaymentLink')
            ->with($token, $order->getPayment());

        $this->handler->process($order, self::SESSION_ID);
    }

    public function testDoesNothingWhenTheTokenWasNotSaved(): void
    {
        $this->paymentSessions->method('getSavedToken')->willReturn(null);
        $this->tokenManagement->expects($this->never())->method('saveTokenWithPaymentLink');

        $this->handler->process($this->anOrder(42, '1'), self::SESSION_ID);
    }

    public function testDoesNothingWhenTheSaveFlagIsAbsent(): void
    {
        $this->paymentSessions->expects($this->never())->method('getSavedToken');
        $this->tokenManagement->expects($this->never())->method('saveTokenWithPaymentLink');

        $this->handler->process($this->anOrder(42, null), self::SESSION_ID);
    }

    public function testDoesNothingForGuestOrders(): void
    {
        $this->paymentSessions->expects($this->never())->method('getSavedToken');
        $this->tokenManagement->expects($this->never())->method('saveTokenWithPaymentLink');

        $this->handler->process($this->anOrder(0, '1'), self::SESSION_ID);
    }

    public function testSdkExceptionIsLoggedAndNotPropagated(): void
    {
        $this->paymentSessions->method('getSavedToken')->willThrowException(new \RuntimeException('boom'));
        $this->logger->expects($this->once())->method('error');
        $this->tokenManagement->expects($this->never())->method('saveTokenWithPaymentLink');

        $this->handler->process($this->anOrder(42, '1'), self::SESSION_ID);
    }

    public function testPersistExceptionIsLoggedAndNotPropagated(): void
    {
        $this->paymentSessions->method('getSavedToken')->willReturn($this->aToken());
        $this->tokenFactory->method('create')->willReturn($this->createMock(PaymentTokenInterface::class));
        $this->tokenManagement->method('saveTokenWithPaymentLink')->willThrowException(new \RuntimeException('db'));
        $this->logger->expects($this->once())->method('error');

        $this->handler->process($this->anOrder(42, '1'), self::SESSION_ID);
    }

    public function testDoesNotCreateADuplicateWhenTheTokenAlreadyExists(): void
    {
        $this->paymentSessions->method('getSavedToken')->willReturn($this->aToken());
        $this->tokenManagement->method('getByGatewayToken')
            ->with('tok_rvvup', 'rvvup_CARD', 42)
            ->willReturn($this->createMock(PaymentTokenInterface::class));
        $this->tokenFactory->expects($this->never())->method('create');
        $this->tokenManagement->expects($this->never())->method('saveTokenWithPaymentLink');

        $this->handler->process($this->anOrder(42, '1'), self::SESSION_ID);
    }

    /**
     * @dataProvider brandProvider
     */
    public function testMapsCardBrands(string $brand, string $expected): void
    {
        $this->assertSame($expected, $this->handler->mapCardType($brand));
    }

    public function brandProvider(): array
    {
        return [
            ['visa', 'VI'],
            ['mastercard', 'MC'],
            ['MasterCard', 'MC'],
            ['amex', 'AE'],
            ['american_express', 'AE'],
            ['unknown-brand', 'OT'],
            ['', 'OT'],
        ];
    }

    public function testExpiresAtIsTheEndOfTheExpiryMonth(): void
    {
        $this->assertSame('2035-01-01 00:00:00', $this->handler->getExpiresAt('12', '2034'));
        $this->assertSame('2030-03-01 00:00:00', $this->handler->getExpiresAt('02', '2030'));
    }

    private function aToken(): PaymentMethodTokenDto
    {
        $dto = new PaymentMethodTokenDto();
        $dto->setId('tok_rvvup');
        $dto->setTokenId('bt_provider');
        $dto->setCardBrand('visa');
        $dto->setCardLast4('1111');
        $dto->setCardExpiryMonth('12');
        $dto->setCardExpiryYear('2034');

        return $dto;
    }

    /**
     * @return Order|MockObject
     */
    private function anOrder(int $customerId, ?string $saveFlag)
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getAdditionalInformation')->willReturnCallback(
            static fn ($key) => $key === Method::SAVE_TOKEN_REQUESTED ? $saveFlag : null
        );

        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getCustomerId')->willReturn($customerId);
        $order->method('getStoreId')->willReturn(1);

        return $order;
    }
}
