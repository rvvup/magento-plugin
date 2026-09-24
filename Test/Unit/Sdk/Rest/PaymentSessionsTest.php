<?php declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Sdk\Rest;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Sdk\Rest\PaymentSessions;

class PaymentSessionsTest extends TestCase
{
    private const BASE_URL = 'https://api.rvvup.com';
    private const MERCHANT_ID = 'merchant-123';
    private const AUTH_TOKEN = 'test.jwt.token';
    private const PAYMENT_SESSION_ID = 'ps-abc-456';

    private function makeSubject(ClientInterface $http): PaymentSessions
    {
        return new PaymentSessions(self::BASE_URL, self::MERCHANT_ID, self::AUTH_TOKEN, $http);
    }

    public function testGetSavedTokenReturnsArrayOnSuccess(): void
    {
        $payload = [
            'id' => 'tok-111',
            'cardLast4' => '4242',
            'cardExpiryMonth' => '12',
            'cardExpiryYear' => '2029',
            'cardBrand' => 'VISA',
        ];

        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                self::BASE_URL . '/api/2024-03-01/' . self::MERCHANT_ID
                    . '/payment-sessions/' . self::PAYMENT_SESSION_ID . '/saved-token',
                $this->arrayHasKey('headers')
            )
            ->willReturn(new Response(200, [], json_encode($payload)));

        $result = $this->makeSubject($http)->getSavedToken(self::PAYMENT_SESSION_ID);

        $this->assertSame($payload, $result);
    }

    public function testGetSavedTokenReturnsNullOn404(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->willThrowException(new ClientException(
                'Not Found',
                new Request('GET', '/'),
                new Response(404)
            ));

        $result = $this->makeSubject($http)->getSavedToken(self::PAYMENT_SESSION_ID);

        $this->assertNull($result);
    }

    public function testGetSavedTokenThrowsOnNon404ClientError(): void
    {
        $this->expectException(ClientException::class);

        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->willThrowException(new ClientException(
                'Unauthorized',
                new Request('GET', '/'),
                new Response(401)
            ));

        $this->makeSubject($http)->getSavedToken(self::PAYMENT_SESSION_ID);
    }

    public function testGetSavedTokenSendsAuthorizationHeader(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->anything(),
                $this->callback(function (array $options): bool {
                    return ($options['headers']['Authorization'] ?? '') === 'Bearer ' . self::AUTH_TOKEN;
                })
            )
            ->willReturn(new Response(200, [], '{}'));

        $this->makeSubject($http)->getSavedToken(self::PAYMENT_SESSION_ID);
    }
}
