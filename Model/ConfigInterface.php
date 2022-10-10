<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Store\Model\ScopeInterface;

interface ConfigInterface
{
    public const RVVUP_CONFIG = 'payment/rvvup/';
    public const XML_PATH_ACTIVE = 'active';
    public const XML_PATH_DEBUG = 'debug';
    public const XML_PATH_JWT = 'jwt';

    /**
     * Validate whether Rvvup module & payment methods are active.
     *
     * @param string $scopeType
     * @param string|null $scopeCode The store's code or storeId as a string
     * @return bool
     */
    public function isActive(string $scopeType = ScopeInterface::SCOPE_STORE, string $scopeCode = null): bool;

    /**
     * Get the active value from the config.
     *
     * @param string $scopeType
     * @param string|null $scopeCode The store's code or storeId as a string
     * @return bool
     */
    public function getActiveConfig(string $scopeType = ScopeInterface::SCOPE_STORE, string $scopeCode = null): bool;

    /**
     * Get the JWT value from the config.
     *
     * @param string $scopeType
     * @param string|null $scopeCode The store's code or storeId as a string
     * @return string|null
     */
    public function getJwtConfig(string $scopeType = ScopeInterface::SCOPE_STORE, string $scopeCode = null): ?string;

    /**
     * Get the endpoint URL.
     *
     * @param string $scopeType
     * @param string|null $scopeCode The store's code or storeId as a string
     * @return string
     */
    public function getEndpoint(string $scopeType = ScopeInterface::SCOPE_STORE, string $scopeCode = null): string;

    /**
     * Get the Merchant ID.
     *
     * @param string $scopeType
     * @param string|null $scopeCode The store's code or storeId as a string
     * @return string
     */
    public function getMerchantId(string $scopeType = ScopeInterface::SCOPE_STORE, string $scopeCode = null): string;

    /**
     * Get the Authorization Token.
     *
     * @param string $scopeType
     * @param string|null $scopeCode The store's code or storeId as a string
     * @return string
     */
    public function getAuthToken(string $scopeType = ScopeInterface::SCOPE_STORE, string $scopeCode = null): string;

    /**
     * Check whether debug mode is enabled.
     *
     * @param string $scopeType
     * @param string|null $scopeCode The store's code or storeId as a string
     * @return bool
     */
    public function isDebugEnabled(string $scopeType = ScopeInterface::SCOPE_STORE, string $scopeCode = null): bool;
}
