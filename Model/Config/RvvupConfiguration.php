<?php

namespace Rvvup\Payments\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class RvvupConfiguration implements RvvupConfigurationInterface
{
    /**
     * @var array
     */
    private $rvvupDecodedPayloadByStoreMap;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface  $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
        $this->rvvupDecodedPayloadByStoreMap = [];
    }

    /**
     * @inheritDoc
     */
    public function getMerchantId(int $storeId): ?string
    {
        return $this->getStringConfigFromJwt($storeId, "merchantId");
    }

    /**
     * @inheritDoc
     */
    public function getRestApiUrl(int $storeId): ?string
    {
        $audience = $this->getStringConfigFromJwt($storeId, "aud");
        if ($audience == null) {
            return null;
        }
        return str_replace('graphql', 'api/2024-03-01', $audience);
    }

    /**
     * @inheritDoc
     */
    public function getBearerToken(int $storeId): ?string
    {
        $value = $this->scopeConfig->getValue(self::RVVUP_CONFIG_PATH . "jwt", ScopeInterface::SCOPE_STORE, $storeId);

        if ($value === null) {
            return null;
        }

        // JWT should be a string.
        if (!is_string($value)) {
            return null;
        }

        $trimmedValue = trim($value);

        if ($trimmedValue === '') {
            return null;
        }

        return $trimmedValue;
    }

    /**
     * @param int $storeId store id
     * @param string $configKey config key
     * @return string|null string config value or null if not found
     */
    private function getStringConfigFromJwt(int $storeId, string $configKey): ?string
    {
        $jwt = $this->getDecodedPayload($storeId);
        if ($jwt == null) {
            return null;
        }
        return $jwt[$configKey];
    }

    /**
     * Get decoded payload
     * @param int $storeId store id
     * @return array|null payload
     */
    private function getDecodedPayload(int $storeId): ?array
    {
        if (isset($this->rvvupDecodedPayloadByStoreMap[$storeId])) {
            return $this->rvvupDecodedPayloadByStoreMap[$storeId];
        }
        $jwt = $this->getBearerToken($storeId);
        if ($jwt == null) {
            return null;
        }
        $parts = explode('.', $jwt);
        if (!$parts || count($parts) <= 1) {
            return null;
        }
        $payload = $parts[1];
        if ($payload == null) {
            return null;
        }
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
        $decodedPayload = json_decode(base64_decode($payload), true);
        $this->rvvupDecodedPayloadByStoreMap[$storeId] = $decodedPayload;
        return $this->rvvupDecodedPayloadByStoreMap[$storeId];
    }
}
