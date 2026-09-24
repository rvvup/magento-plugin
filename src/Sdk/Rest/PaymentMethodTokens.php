<?php declare(strict_types=1);

namespace Rvvup\Payments\Sdk\Rest;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;

class PaymentMethodTokens
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
     * Returns false when the token was not found (already revoked or never existed).
     *
     * @throws ClientException on non-404 errors
     */
    public function revoke(string $rvvupTokenId): bool
    {
        $url = $this->baseUrl
            . '/api/2024-03-01/' . rawurlencode($this->merchantId)
            . '/payment-method-tokens/' . rawurlencode($rvvupTokenId);

        try {
            $this->http->request('DELETE', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->authToken,
                    'Accept' => 'application/json',
                ],
            ]);
            return true;
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return false;
            }
            throw $e;
        }
    }
}
