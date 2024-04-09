<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Environment;

use Exception;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use Rvvup\Payments\Model\Logger;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Filesystem\Io\IoInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Composer\ComposerInformation;
use Magento\Framework\Serialize\SerializerInterface;

class GetEnvironmentVersions implements GetEnvironmentVersionsInterface
{
    /**
     * @var \Magento\Framework\App\CacheInterface
     */
    private $cache;

    /**
     * @var \Magento\Framework\App\ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @var \Magento\Framework\Composer\ComposerInformation
     */
    private $composerInformation;

    /**
     * Set via di.xml
     *
     * @var \Magento\Framework\Filesystem\Io\IoInterface|\Magento\Framework\Filesystem\Io\File
     */
    private $fileIo;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * Set via di.xml
     *
     * @var \Psr\Log\LoggerInterface|Logger
     */
    private $logger;

    /**
     * @var string|null
     */
    private $cachedEnvironmentVersions;

    /**
     * @param \Magento\Framework\App\CacheInterface $cache
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadata
     * @param \Magento\Framework\Composer\ComposerInformation $composerInformation
     * @param \Magento\Framework\Filesystem\Io\IoInterface $fileIo
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Psr\Log\LoggerInterface $logger
     * @return void
     */
    public function __construct(
        CacheInterface $cache,
        ProductMetadataInterface $productMetadata,
        ComposerInformation $composerInformation,
        IoInterface $fileIo,
        SerializerInterface $serializer,
        LoggerInterface $logger
    ) {
        $this->cache = $cache;
        $this->productMetadata = $productMetadata;
        $this->composerInformation = $composerInformation;
        $this->fileIo = $fileIo;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * Get a list of the environment versions including module, magento, and php versions
     *
     * @return array
     */
    public function execute(): array
    {
        $cachedEnvironmentVersions = $this->getCachedEnvironmentVersions();

        // If not null data in cache, return them unserialized.
        if ($cachedEnvironmentVersions !== null) {
            try {
                return $this->serializer->unserialize($cachedEnvironmentVersions);
            } catch (InvalidArgumentException $ex) {
                // Fail silently and allow the rest of the code to get the data;
                $this->logger->error('Failed to decode `cachedEnvironmentVersions` with message: ' . $ex->getMessage());
            }
        }

        $environmentVersions = [
            'rvvp_module_version' => $this->getRvvupModuleVersion(),
            'php_version' => phpversion(),
            'magento_version' => [
                'name' => $this->productMetadata->getName(),
                'edition' => $this->productMetadata->getEdition(),
                'version' => $this->productMetadata->getVersion()
            ]
        ];

        try {
            $this->cache->save($this->serializer->serialize($environmentVersions), self::RVVUP_ENVIRONMENT_VERSIONS);
        } catch (InvalidArgumentException $ex) {
            $this->logger->error(
                'Failed to serialize & save environment version data to cache with message: ' . $ex->getMessage(),
                $environmentVersions
            );
        }

        return $environmentVersions;
    }

    /**
     * Get & set property environment versions from cache.
     * Null it if not string or no value.
     *
     * @return string|null
     */
    private function getCachedEnvironmentVersions(): ?string
    {
        if ($this->cachedEnvironmentVersions === null) {
            $this->cachedEnvironmentVersions = $this->cache->load(self::RVVUP_ENVIRONMENT_VERSIONS);
        }

        if (!is_string($this->cachedEnvironmentVersions) || empty($this->cachedEnvironmentVersions)) {
            $this->cachedEnvironmentVersions = null;
        }

        return $this->cachedEnvironmentVersions;
    }

    /**
     * Get the Rvvup Module version installed either from project's `composer.lock` or `app/code` folder installation.
     *
     * Fallback to unknown.
     *
     * @return string
     */
    public function getRvvupModuleVersion(): string
    {
        // Attempt to figure out what plugin version we have depending on installation method
        $packages = $this->composerInformation->getInstalledMagentoPackages();

        // Get the value from the composer.lock file if set.
        if (isset($packages['rvvup/module-magento-payments']['version'])
            && is_string($packages['rvvup/module-magento-payments']['version'])
        ) {
            return (string) $packages['rvvup/module-magento-payments']['version'];
        }

        // Otherwise, check for `app/code` installation
        $appCodeComposerJsonVersion = $this->getAppCodeComposerJsonVersion();

        // If set use it, otherwise unknown.
        return $appCodeComposerJsonVersion ?? self::UNKNOWN_VERSION;
    }

    /**
     * Get the version from the `composer.json` of the module if module is installed `in app/code`.
     *
     * @return string|null
     */
    private function getAppCodeComposerJsonVersion(): ?string
    {
        // We need to get 2 folders up to `app/code/Rvvup/Payments`,
        // now we're in  `app/code/Rvvup/Payments/Model/Environment`
        $fileName = __DIR__
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'composer.json';

        if (!$this->fileIo->fileExists($fileName)) {
            return null;
        }

        try {
            $composerFile = $this->fileIo->read($fileName);

            if (!is_string($composerFile)) {
                $this->logger->debug('Failed to read composer file from `app/code` directory');

                return null;
            }

            try {
                $composerData = $this->serializer->unserialize($composerFile);
            } catch (InvalidArgumentException $ex) {
                $this->logger->debug('Failed to unserialize content of composer file from `app/code` directory');

                return null;
            }

            return is_array($composerData) && isset($composerData['version']) && is_string($composerData['version'])
                ? (string) $composerData['version']
                : null;
        } catch (Exception $e) {
            return null;
        }
    }
}
