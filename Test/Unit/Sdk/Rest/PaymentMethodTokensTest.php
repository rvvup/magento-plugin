<?php declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Sdk\Rest;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Sdk\Rest\PaymentMethodTokens;

class PaymentMethodTokensTest extends TestCase
{
    private const BASE_URL = 'https://api.rvvup.com';
    private const MERCHANT_ID = 'merchant-123';
    private const AUTH_TOKEN = 'test.jwt.token';
    private const TOKEN_ID = 'tok-xyz-789';

    private function makeSubject(ClientInterface $http): PaymentMethodTokens
    {
        return new PaymentMethodTokens(self::BASE_URL, self::MERCHANT_ID, self::AUTH_TOKEN, $http);
    }

    public function testRevokeReturnsTrueOn204(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->with(
                'DELETE',
                self::BASE_URL . '/api/2024-03-01/' . self::MERCHANT_ID
                    . '/payment-method-tokens/' . self::TOKEN_ID,
                $this->arrayHasKey('headers')
            )
            ->willReturn(new Response(204));

        $this->assertTrue($this->makeSubject($http)->revoke(self::TOKEN_ID));
    }

    public function testRevokeReturnsFalseOn404(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->willThrowException(new ClientException(
                'Not Found',
                new Request('DELETE', '/'),
                new Response(404)
            ));

        $this->assertFalse($this->makeSubject($http)->revoke(self::TOKEN_ID));
    }

    public function testRevokeThrowsOnNon404ClientError(): void
    {
        $this->expectException(ClientException::class);

        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->willThrowException(new ClientException(
                'Forbidden',
                new Request('DELETE', '/'),
                new Response(403)
            ));

        $this->makeSubject($http)->revoke(self::TOKEN_ID);
    }

    public function testRevokeSendsAuthorizationHeader(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->with(
                'DELETE',
                $this->anything(),
                $this->callback(function (array $options): bool {
                    return ($options['headers']['Authorization'] ?? '') === 'Bearer ' . self::AUTH_TOKEN;
                })
            )
            ->willReturn(new Response(204));

        $this->makeSubject($http)->revoke(self::TOKEN_ID);
    }
}
