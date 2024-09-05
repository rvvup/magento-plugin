<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use stdClass;

class Config implements ConfigInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \stdClass|null
     */
    private $jwt;

    private $rvvupJwtByStoreMap;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->rvvupJwtByStoreMap = [];
    }

    /**
     * Validate whether Rvvup module & payment methods are active.
     *
     * @param string $scopeType
     * @param string|null $scopeCode The store's code or storeId as a string
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isActive(string $scopeType = ScopeInterface::SCOPE_STORE, string $scopeCode = null): bool
    {
        if (!$this->getActiveConfig($scopeType)) {
            return false;
        }

        $jwt = $this->getJwt($scopeType);
        return (bool)$jwt;
    }

    /**
     * Get the active value from the config.
     *
     * @param string $scopeType
     * @return bool
     * @throws NoSuchEntityException
     */
    public function getActiveConfig(string $scopeType = ScopeInterface::SCOPE_STORE): bool
    {
        $scopeCode = $this->storeManager->getStore() ? $this->storeManager->getStore()->getCode() : null;
        return (bool) $this->scopeConfig->getValue(self::RVVUP_CONFIG . self::XML_PATH_ACTIVE, $scopeType, $scopeCode);
    }

    /**
     * Get the JWT value from the config.
     *
     * @param string $scopeType
     * @param string|null $scopeCode
     * @return string|null
     * @throws NoSuchEntityException
     */
    public function getJwtConfig(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        string $scopeCode = null
    ): ?string {
        $scopeCode = $scopeCode ?:
            ($this->storeManager->getStore() ? $this->storeManager->getStore()->getCode() : null);

        $value = $this->scopeConfig->getValue(self::RVVUP_CONFIG . self::XML_PATH_JWT, $scopeType, $scopeCode);

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

    private function getJwtConfigByStore(int $storeId): ?string
    {
        $value = $this->scopeConfig->getValue(self::RVVUP_CONFIG . self::XML_PATH_JWT, ScopeInterface::SCOPE_STORE, $storeId);

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
     * Get the endpoint URL.
     *
     * @param string $scopeType
     * @param string|null $scopeCode
     * @return string
     * @throws NoSuchEntityException
     */
    public function getEndpoint(string $scopeType = ScopeInterface::SCOPE_STORE, string $scopeCode = null): string
    {
        $jwt = $this->getJwt($scopeType, $scopeCode);

        return $jwt === null ? '' : (string) $jwt->aud;
    }

    /**
     * Get the Merchant ID.
     *
     * @param string $scopeType
     * @param string|null $scopeCode
     * @return string
     * @throws NoSuchEntityException
     */
    public function getMerchantId(string $scopeType = ScopeInterface::SCOPE_STORE, string $scopeCode = null): string
    {
        $jwt = $this->getJwt($scopeType, $scopeCode);

        return $jwt === null ? '' : (string) $jwt->merchantId;
    }

    public function getMerchantIdByStore(int $storeId): ?string
    {
        return $this->getStringConfigFromJwt($storeId, "merchantId");
    }


    /**
     * Get the Authorization Token.
     *
     * @param string $scopeType
     * @param string|null $scope
     * @return string
     * @throws NoSuchEntityException
     */
    public function getAuthToken(string $scopeType = ScopeInterface::SCOPE_STORE, string $scope = null): string
    {
        $jwt = $this->getJwt($scopeType, $scope);

        if ($jwt === null) {
            return '';
        }

        return base64_encode($jwt->username . ':' . $jwt->password);
    }

    /**
     * Check whether debug mode is enabled.
     *
     * @param string $scopeType
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isDebugEnabled(string $scopeType = ScopeInterface::SCOPE_STORE): bool
    {
        $scopeCode = $this->storeManager->getStore() ? $this->storeManager->getStore()->getCode() : null;
        return (bool) $this->scopeConfig->getValue(self::RVVUP_CONFIG . self::XML_PATH_DEBUG, $scopeType, $scopeCode);
    }

    /**
     * Get a standard class by decoding the config JWT.
     *
     * @param string $scopeType
     * @param string $scopeCode
     * @return \stdClass|null
     * @throws NoSuchEntityException
     */
    private function getJwt(string $scopeType = ScopeInterface::SCOPE_STORE, string $scopeCode = null): ?stdClass
    {
        if (!$scopeCode) {
            $scopeCode = $this->storeManager->getStore()->getId();
        }

        if (!isset($this->jwt[$scopeCode])) {
            $jwt = $this->getJwtConfig($scopeType, $scopeCode);

            if ($jwt === null) {
                $this->jwt[$scopeCode] = null;

                return $this->jwt[$scopeCode];
            }

            $parts = explode('.', $jwt);
            list($head, $body, $crypto) = $parts;
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            $this->jwt[$scopeCode] = json_decode(base64_decode($body));
        }

        return $this->jwt[$scopeCode];
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
        if (isset($this->rvvupJwtByStoreMap[$storeId])) {
            return $this->rvvupJwtByStoreMap[$storeId];
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
        $this->rvvupJwtByStoreMap[$storeId] = $decodedPayload;
        return $this->rvvupJwtByStoreMap[$storeId];
    }


    /**
     *
     * @param string $config
     * @return string
     */
    public function getPaypalBlockStyling(string $config): string
    {
        if ($config == self::XML_PATH_STYLE) {
            if ($this->getPayPalBlockConfig(self::XML_PATH_USE_PLACE_ORDER_STYLING)) {
                return $this->getPayPalBlockConfig(self::XML_PATH_PLACE_ORDER_STYLING);
            }
            return $this->getPayPalBlockConfig(self::XML_PATH_STYLE);
        }
        return $this->getPayPalBlockConfig($config);
    }

    public function getPayPalBlockConfig(
        string $config,
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): string {
        $config = self::RVVUP_CONFIG . self::XML_PATH_PAYPAL_BLOCK . $config;
        $scopeCode = $this->storeManager->getStore() ? $this->storeManager->getStore()->getCode() : null;

        return $this->scopeConfig->getValue($config, $scopeType, $scopeCode);
    }

    /**
     * @param string $scopeType
     * @return array
     * @throws NoSuchEntityException
     */
    public function getValidProductTypes(
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): array {
        $scopeCode = $this->storeManager->getStore() ? $this->storeManager->getStore()->getCode() : null;
        $path = self::RVVUP_CONFIG . self::PRODUCT_RESTRICTIONS . self::XML_PATH_PRODUCT_TYPES_ENABLED;
        $types = $this->scopeConfig->getValue($path, $scopeType, $scopeCode);
        return explode(',', $types);
    }

    /**
     * @param string $scopeType
     * @param string|null $scopeCode
     * @return string
     * @throws NoSuchEntityException
     */
    public function getPayByLinkText(string $scopeType = ScopeInterface::SCOPE_STORE, string $scopeCode = null): string
    {
        $config = self::RVVUP_CONFIG . self::XML_PATH_EMAIL . self::XML_PATH_PAY_BY_LINK_TEXT;
        $scopeCode = $scopeCode ?:
            ($this->storeManager->getStore() ? $this->storeManager->getStore()->getCode() : null);

        return $this->scopeConfig->getValue($config, $scopeType, $scopeCode);
    }
}
