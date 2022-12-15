<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use stdClass;

class Config implements ConfigInterface
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \stdClass|null
     */
    private $jwt;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @return void
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Validate whether Rvvup module & payment methods are active.
     *
     * @param string $scopeType
     * @param string|null $scopeCode The store's code or storeId as a string
     * @return bool
     */
    public function isActive(string $scopeType = ScopeInterface::SCOPE_STORE, string $scopeCode = null): bool
    {
        if (!$this->getActiveConfig($scopeType, $scopeCode)) {
            return false;
        }

        $jwt = $this->scopeConfig->getValue(self::RVVUP_CONFIG . self::XML_PATH_JWT);
        if (!is_string($jwt)) {
            return false;
        }
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return false;
        }
        list($head, $body, $crypto) = $parts;
        try {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            $this->jwt = json_decode(base64_decode($body), false, 2, JSON_THROW_ON_ERROR);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the active value from the config.
     *
     * @param string $scopeType
     * @param string|null $scopeCode The store's code or ID as a string
     * @return bool
     */
    public function getActiveConfig(string $scopeType = ScopeInterface::SCOPE_STORE, string $scopeCode = null): bool
    {
        return (bool) $this->scopeConfig->getValue(self::RVVUP_CONFIG . self::XML_PATH_ACTIVE, $scopeType, $scopeCode);
    }

    /**
     * Get the JWT value from the config.
     *
     * @param string $scopeType
     * @param string|null $scopeCode The store's code or storeId as a string
     * @return string|null
     */
    public function getJwtConfig(string $scopeType = ScopeInterface::SCOPE_STORE, string $scopeCode = null): ?string
    {
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

    /**
     * Get the endpoint URL.
     *
     * @param string $scopeType
     * @param string|null $scopeCode The store's code or ID as a string
     * @return string
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
     * @param string|null $scopeCode The store's code or ID as a string
     * @return string
     */
    public function getMerchantId(string $scopeType = ScopeInterface::SCOPE_STORE, string $scopeCode = null): string
    {
        $jwt = $this->getJwt($scopeType, $scopeCode);

        return $jwt === null ? '' : (string) $jwt->merchantId;
    }

    /**
     * Get the Authorization Token.
     *
     * @param string $scopeType
     * @param string|null $scopeCode The store's code or ID as a string
     * @return string
     */
    public function getAuthToken(string $scopeType = ScopeInterface::SCOPE_STORE, string $scopeCode = null): string
    {
        $jwt = $this->getJwt($scopeType, $scopeCode);

        if ($jwt === null) {
            return '';
        }

        return base64_encode($jwt->username . ':' . $jwt->password);
    }

    /**
     * Check whether debug mode is enabled.
     *
     * @param string $scopeType
     * @param string|null $scopeCode The store's code or ID as a string
     * @return bool
     */
    public function isDebugEnabled(string $scopeType = ScopeInterface::SCOPE_STORE, string $scopeCode = null): bool
    {
        return (bool) $this->scopeConfig->getValue(self::RVVUP_CONFIG . self::XML_PATH_DEBUG, $scopeType, $scopeCode);
    }

    /**
     * Get a standard class by decoding the config JWT.
     *
     * @param string $scopeType
     * @param string|null $scopeCode The store's code or ID as a string
     *
     * @return \stdClass|null
     */
    private function getJwt(string $scopeType = ScopeInterface::SCOPE_STORE, string $scopeCode = null): ?stdClass
    {
        if (!$this->jwt || $scopeType !== ScopeInterface::SCOPE_STORE || $scopeCode !== null) {
            $jwt = $this->getJwtConfig($scopeType, $scopeCode);

            if ($jwt === null) {
                $this->jwt = null;

                return $this->jwt;
            }

            $parts = explode('.', $jwt);
            list($head, $body, $crypto) = $parts;
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            $this->jwt = json_decode(base64_decode($body));
        }

        return $this->jwt;
    }

    /**
     * @param string $scopeType
     * @param string|null $scopeCode
     * @return bool
     */
    public function getValidProductTypes(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        string $scopeCode = null
    ): array {
        $path = self::RVVUP_CONFIG . self::PRODUCT_RESTRICTIONS . self::XML_PATH_PRODUCT_TYPES_ENABLED;
        $types = $this->scopeConfig->getValue($path, $scopeType, $scopeCode);
        return explode(',', $types);
    }
}
