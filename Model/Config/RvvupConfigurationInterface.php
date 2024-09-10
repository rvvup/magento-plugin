<?php

namespace Rvvup\Payments\Model\Config;

interface RvvupConfigurationInterface
{
    public const RVVUP_CONFIG_PATH = 'payment/rvvup/';

    /**
     * Get the Merchant ID by store ID.
     *
     * @param int $storeId to get the config for
     * @return string|null The merchant ID or NULL if not found
     */
    public function getMerchantId(int $storeId): ?string;

    /**
     * Get rest API url by store ID.
     * @param int $storeId to get the config for
     * @return string|null The rest API url or NULL if not found
     */
    public function getRestApiUrl(int $storeId): ?string;

    /**
     * Get raw api token by store ID.
     * @param int $storeId to get the config for
     * @return string|null The raw api token or NULL if not found
     */
    public function getBearerToken(int $storeId): ?string;
}
