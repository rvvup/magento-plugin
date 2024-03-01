<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Store\Model\ScopeInterface;

interface ConfigInterface
{
    public const RVVUP_CONFIG = 'payment/rvvup/';
    public const PRODUCT_RESTRICTIONS = 'product_restrictions/';
    public const XML_PATH_ACTIVE = 'active';
    public const XML_PATH_DEBUG = 'debug';
    public const XML_PATH_JWT = 'jwt';
    public const XML_PATH_PRODUCT_TYPES_ENABLED = 'enabled_product_types';
    public const XML_PATH_PAYPAL_BLOCK = 'paypal_block/';
    public const XML_PATH_EMAIL = 'email/';
    public const XML_PATH_STYLE = 'style';
    public const XML_PATH_BACKGROUND_STYLING = 'background_styling';
    public const XML_PATH_BORDER_STYLING = 'border_styling';
    public const XML_PATH_USE_PLACE_ORDER_STYLING = 'use_place_order_styling';
    public const XML_PATH_PLACE_ORDER_STYLING = 'place_order_styling';
    public const XML_PATH_PAY_BY_LINK_TEXT = 'pay_by_link';

    /**
     * Validate whether Rvvup module & payment methods are active.
     *
     * @param string $scopeType
     * @return bool
     */
    public function isActive(string $scopeType = ScopeInterface::SCOPE_STORE): bool;

    /**
     * Get the active value from the config.
     *
     * @param string $scopeType
     * @return bool
     */
    public function getActiveConfig(string $scopeType = ScopeInterface::SCOPE_STORE): bool;

    /**
     * Get the JWT value from the config.
     *
     * @param string $scopeType
     * @return string|null
     */
    public function getJwtConfig(string $scopeType = ScopeInterface::SCOPE_STORE): ?string;

    /**
     * Get the endpoint URL.
     *
     * @param string $scopeType
     * @return string
     */
    public function getEndpoint(string $scopeType = ScopeInterface::SCOPE_STORE): string;

    /**
     * Get the Merchant ID.
     *
     * @param string $scopeType
     * @return string
     */
    public function getMerchantId(string $scopeType = ScopeInterface::SCOPE_STORE): string;

    /**
     * Get the Authorization Token.
     *
     * @param string $scopeType
     * @return string
     */
    public function getAuthToken(string $scopeType = ScopeInterface::SCOPE_STORE): string;

    /**
     * Check whether debug mode is enabled.
     *
     * @param string $scopeType
     * @return bool
     */
    public function isDebugEnabled(string $scopeType = ScopeInterface::SCOPE_STORE): bool;

    /**
     * Get style for paypal button
     * @param string $config
     * @return string
     */
    public function getPaypalBlockStyling(string $config): string;

    /**
     * Get text for pay-by-link emails
     * @param string $scopeType
     * @return string
     */
    public function getPayByLinkText(string $scopeType = ScopeInterface::SCOPE_STORE): string;

    /**
     * Get valid product types
     * @param string $scopeType
     * @return array
     */
    public function getValidProductTypes(
        string $scopeType = ScopeInterface::SCOPE_STORE
    ): array;
}
