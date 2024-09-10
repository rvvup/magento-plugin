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
     * Get the Authorization Token.
     *
     * @param int $storeId to get the config for
     * @return string|null
     */
    public function getAuthToken(int $storeId): ?string;
}
