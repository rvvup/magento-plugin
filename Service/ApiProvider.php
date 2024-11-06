<?php declare(strict_types=1);

namespace Rvvup\Payments\Service;

use Exception;
use Rvvup\Payments\Model\Config\RvvupConfigurationInterface;
use Rvvup\Payments\Model\Environment\GetEnvironmentVersionsInterface;
use Rvvup\Payments\Model\UserAgentBuilder;
use Rvvup\Sdk\Rest\RvvupClient;

class ApiProvider
{
    /** @var RvvupConfigurationInterface */
    private $config;
    /** @var UserAgentBuilder */
    private $userAgent;
    /** @var GetEnvironmentVersionsInterface */
    private $getEnvironmentVersions;

    /** @var array */
    private $clients;

    /**
     * @param RvvupConfigurationInterface $config
     * @param UserAgentBuilder $userAgent
     * @param GetEnvironmentVersionsInterface $getEnvironmentVersions
     */
    public function __construct(
        RvvupConfigurationInterface     $config,
        UserAgentBuilder                $userAgent,
        GetEnvironmentVersionsInterface $getEnvironmentVersions
    ) {
        $this->config = $config;
        $this->userAgent = $userAgent;
        $this->getEnvironmentVersions = $getEnvironmentVersions;
        $this->clients = [];
    }

    /**
     * Clean the proxy caches
     */
    public function clean()
    {
        $this->clients = [];
    }

    /**
     * @param string $storeId
     * @return RvvupClient
     * @throws Exception
     */
    public function getSdk(string $storeId): RvvupClient
    {
        if (!isset($this->clients[$storeId])) {
            $this->clients[$storeId] = new RvvupClient($this->config->getBearerToken($storeId), null);
        }
        return $this->clients[$storeId];
    }
}
