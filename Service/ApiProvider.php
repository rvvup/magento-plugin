<?php declare(strict_types=1);

namespace Rvvup\Payments\Service;

use Exception;
use Rvvup\Payments\Model\Config\RvvupConfigurationInterface;
use Rvvup\Payments\Model\UserAgentBuilder;
use Rvvup\Sdk\Rest\Options\RvvupClientOptions;
use Rvvup\Sdk\Rest\RvvupClient;

class ApiProvider
{
    /** @var RvvupConfigurationInterface */
    private $config;
    /** @var UserAgentBuilder */
    private $userAgent;

    /** @var array */
    private $clients;

    /**
     * @param RvvupConfigurationInterface $config
     * @param UserAgentBuilder $userAgent
     */
    public function __construct(
        RvvupConfigurationInterface     $config,
        UserAgentBuilder $userAgent
    ) {
        $this->config = $config;
        $this->userAgent = $userAgent;
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
            $this->clients[$storeId] = new RvvupClient(
                $this->config->getBearerToken($storeId),
                new RvvupClientOptions(null, null, $this->userAgent->get())
            );
        }
        return $this->clients[$storeId];
    }
}
