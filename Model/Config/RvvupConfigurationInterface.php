<?php

namespace Rvvup\Payments\Model\Config;

interface RvvupConfigurationInterface
{
    public const RVVUP_CONFIG_PATH = 'payment/rvvup/';

    /**
     * @param string $storeId
     * @return bool whether debug is enabled
     */
    public function isDebugEnabled(string $storeId): bool;
    /**
     * Get the Merchant ID by store ID.
     *
     * @param string $storeId to get the config for
     * @return string|null The merchant ID or NULL if not found
     */
    public function getMerchantId(string $storeId): ?string;

    /**
     * Get rest API url by store ID.
     * @param string $storeId to get the config for
     * @return string|null The rest API url or NULL if not found
     */
    public function getRestApiUrl(string $storeId): ?string;

    /**
     * Get GraphQL API url by store ID.
     * @param string $storeId to get the config for
     * @return string|null The GraphQL API url or NULL if not found
     */
    public function getGraphQlUrl(string $storeId): ?string;

    /**
     * Get raw api token by store ID.
     * @param string $storeId to get the config for
     * @return string|null The raw api token or NULL if not found
     */
    public function getBearerToken(string $storeId): ?string;

    /**
     * Get basic authentication token by store ID.
     * @param string $storeId to get the config for
     * @return string|null The basic authentication token or NULL if not found
     */
    public function getBasicAuthToken(string $storeId): ?string;

    /**
     * Clean the configuration cache.
     */
    public function clean();
}
