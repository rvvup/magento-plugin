<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Environment;

interface GetEnvironmentVersionsInterface
{
    public const RVVUP_ENVIRONMENT_VERSIONS = 'rvvup_environment_versions';
    public const UNKNOWN_VERSION = 'unknown';

    /**
     * Get a list of the environment versions including module, magento, and php versions
     *
     * @return array
     */
    public function execute(): array;
}
