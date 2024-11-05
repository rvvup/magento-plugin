<?php

namespace Rvvup\Payments\Service;

use Exception;
use Magento\Framework\Serialize\SerializerInterface;
use Rvvup\Payments\Model\Config\RvvupConfigurationInterface;
use Rvvup\Payments\Sdk\Curl;

class RvvupRestApi
{

    /** @var SerializerInterface */
    private $json;

    /** @var Curl */
    private $curl;

    /** @var RvvupConfigurationInterface */
    private $config;

    public function __construct(
        SerializerInterface         $json,
        Curl                        $curl,
        RvvupConfigurationInterface $config
    )
    {
        $this->json = $json;
        $this->curl = $curl;
        $this->config = $config;
    }

    /**
     * @throws Exception
     */
    public function createCheckout(string $storeId, array $checkoutInput): ?array
    {
        return $this->doRequest($storeId, "POST", "checkouts", $checkoutInput);
    }

    /**
     * @throws Exception
     */
    public function createPaymentSession(string $storeId, string $checkoutId, array $paymentSessionInput): ?array
    {
        return $this->doRequest($storeId, "POST", "checkouts/$checkoutId/payment-sessions", $paymentSessionInput);
    }

    /**
     * @throws Exception
     */
    private function doRequest(string $storeId, string $method, string $path, array $data)
    {
        $url = $this->getApiUrl($storeId) . "/$path";
        $request = $this->curl->request($method, $url, [
            'headers' => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->config->getBearerToken($storeId)
            ],
            'json' => $data
        ]);
        if ($request->response_code < 200 || $request->response_code > 299) {
            throw new Exception("API request to failed: $method $url with $request->response_code");
        }
        return $this->json->unserialize($request->body);
    }

    private function getApiUrl(string $storeId): string
    {
        $merchantId = $this->config->getMerchantId($storeId);
        $baseUrl = $this->config->getRestApiUrl($storeId);
        return "$baseUrl/$merchantId";
    }
}