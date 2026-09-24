<?php declare(strict_types=1);

namespace Rvvup\Payments\Sdk\Rest;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;

class PaymentSessions
{
    /** @var string */
    private $baseUrl;

    /** @var string */
    private $merchantId;

    /** @var string */
    private $authToken;

    /** @var ClientInterface */
    private $http;

    public function __construct(string $baseUrl, string $merchantId, string $authToken, ClientInterface $http)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->merchantId = $merchantId;
        $this->authToken = $authToken;
        $this->http = $http;
    }

    /**
     * Returns null when no token was saved for this payment session (404).
     *
     * @throws ClientException on non-404 errors
     */
    public function getSavedToken(string $paymentSessionId): ?array
    {
        $url = $this->baseUrl
            . '/api/2024-03-01/' . rawurlencode($this->merchantId)
            . '/payment-sessions/' . rawurlencode($paymentSessionId)
            . '/saved-token';

        try {
            $response = $this->http->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->authToken,
                    'Accept' => 'application/json',
                ],
            ]);
            return json_decode((string) $response->getBody(), true);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }
}
