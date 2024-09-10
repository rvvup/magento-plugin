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

    public function getMerchantId(int $storeId): ?string
    {
        return $this->getStringConfigFromJwt($storeId, "merchantId");
    }

    public function getAuthToken(int $storeId): ?string
    {
        return "";
    }

    private function getStringConfigFromJwt(int $storeId, string $configKey): ?string
    {
        $jwt = $this->getJwtByStore($storeId);
        if ($jwt == null) {
            return null;
        }
        return $jwt[$configKey];
    }

    private function getJwtByStore(int $storeId): ?array
    {
        if (isset($this->rvvupDecodedPayloadByStoreMap[$storeId])) {
            return $this->rvvupDecodedPayloadByStoreMap[$storeId];
        }
        $jwt = $this->getJwtConfigByStore($storeId);
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
        $decodedPayload = json_decode(base64_decode($payload), true);
        $this->rvvupDecodedPayloadByStoreMap[$storeId] = $decodedPayload;
        return $this->rvvupDecodedPayloadByStoreMap[$storeId];
    }

    private function getJwtConfigByStore(int $storeId): ?string
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
}
