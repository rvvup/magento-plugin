<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Framework\App\CacheInterface;
use Rvvup\Payments\Model\Environment\GetEnvironmentVersionsInterface;

class UserAgentBuilder
{
    public const RVVUP_USER_AGENT_STRING = 'rvvup_user_agent_string';

    /**
     * @var \Magento\Framework\App\CacheInterface
     */
    private $cache;

    /**
     * @var \Rvvup\Payments\Model\Environment\GetEnvironmentVersionsInterface
     */
    private $getEnvironmentVersions;

    /**
     * @param \Magento\Framework\App\CacheInterface $cache
     * @param \Rvvup\Payments\Model\Environment\GetEnvironmentVersionsInterface $getEnvironmentVersions
     * @return void
     */
    public function __construct(CacheInterface $cache, GetEnvironmentVersionsInterface $getEnvironmentVersions)
    {
        $this->cache = $cache;
        $this->getEnvironmentVersions = $getEnvironmentVersions;
    }

    /**
     * @return string
     */
    public function get(): string
    {
        // Use pre-generated version if available
        $userAgent = $this->cache->load(self::RVVUP_USER_AGENT_STRING);

        if (is_string($userAgent) && !empty($userAgent)) {
            return $userAgent;
        }

        $environmentVersions = $this->getEnvironmentVersions->execute();

        // Build result
        $parts = [
            'RvvupMagentoPayments/' . $environmentVersions['rvvp_module_version'],
            $environmentVersions['magento_version']['name'] . '-'
            . $environmentVersions['magento_version']['edition'] . '/'
            . $environmentVersions['magento_version']['version'],
            'PHP/' . $environmentVersions['php_version'],
        ];

        $userAgent = implode("; ", $parts);

        // Save to cache and return
        $this->cache->save($userAgent, self::RVVUP_USER_AGENT_STRING);

        return $userAgent;
    }
}
