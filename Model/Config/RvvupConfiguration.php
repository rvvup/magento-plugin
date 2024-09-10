<?php

namespace Rvvup\Payments\Model\Config;

class RvvupConfiguration implements RvvupConfigurationInterface
{
    /**
     * @var array
     */
    private $rvvupJwtByStoreMap;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;



    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->rvvupJwtByStoreMap = [];
    }
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
