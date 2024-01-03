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
     * @return string|null
     */
    public function getJwtConfig(string $scopeType = ScopeInterface::SCOPE_STORE): ?string
    {
        $scopeCode = $this->storeManager->getStore() ? $this->storeManager->getStore()->getCode() : null;

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
     * @return string
     */
    public function getEndpoint(string $scopeType = ScopeInterface::SCOPE_STORE): string
    {
        $jwt = $this->getJwt($scopeType);

        return $jwt === null ? '' : (string) $jwt->aud;
    }

    /**
     * Get the Merchant ID.
     *
     * @param string $scopeType
     * @return string
     */
    public function getMerchantId(string $scopeType = ScopeInterface::SCOPE_STORE): string
    {
        $jwt = $this->getJwt($scopeType);

        return $jwt === null ? '' : (string) $jwt->merchantId;
    }

    /**
     * Get the Authorization Token.
     *
     * @param string $scopeType
     * @return string
     */
    public function getAuthToken(string $scopeType = ScopeInterface::SCOPE_STORE): string
    {
        $jwt = $this->getJwt($scopeType);

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
     *
     * @return \stdClass|null
     */
    private function getJwt(string $scopeType = ScopeInterface::SCOPE_STORE): ?stdClass
    {
        if (!$this->jwt) {
            $jwt = $this->getJwtConfig($scopeType);

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
     * @return bool
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
}
