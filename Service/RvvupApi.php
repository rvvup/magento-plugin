<?php

namespace Rvvup\Payments\Service;

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


    public function createPaymentSession(string $storeId, string $checkoutId, array $paymentSessionInput)
    {
        return $this->doRequest($storeId, "POST", "checkouts/$checkoutId/payment-sessions", $paymentSessionInput);
    }

    private function doRequest(string $storeId, string $method, string $path, array $data)
    {
        $request = $this->curl->request($method, $this->getApiUrl($storeId) . "/$path", [
            'headers' => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->config->getBearerToken($storeId)
            ],
            'json' => $data
        ]);
        return $this->json->unserialize($request->body);
    }

    private function getApiUrl(string $storeId): string
    {
        $merchantId = $this->config->getMerchantId($storeId);
        $baseUrl = $this->config->getRestApiUrl($storeId);
        return "$baseUrl/$merchantId";
    }
}