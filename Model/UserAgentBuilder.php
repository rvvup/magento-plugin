<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Composer\ComposerInformation;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * It is possible that the merchant may ignore the installation instructions and install directly in the `app/code`
 * directory, so we need to have a fallback to identify the current version by loading the `composer.json` ourselves.
 */
class UserAgentBuilder
{
    public const RVVUP_USER_AGENT_STRING = 'rvvup_user_agent_string';

    /** @var CacheInterface */
    private $cache;
    /** @var ComposerInformation */
    private $composerInformation;
    /** @var File */
    private $fileIo;
    /** @var SerializerInterface */
    private $serializer;
    /** @var ProductMetadataInterface */
    private $platform;

    /**
     * @param CacheInterface $cache
     * @param ComposerInformation $composerInformation
     * @param File $fileIo
     * @param SerializerInterface $serializer
     * @param ProductMetadataInterface $platform
     */
    public function __construct(
        CacheInterface $cache,
        ComposerInformation $composerInformation,
        File $fileIo,
        SerializerInterface $serializer,
        ProductMetadataInterface $platform
    ) {
        $this->cache = $cache;
        $this->composerInformation = $composerInformation;
        $this->fileIo = $fileIo;
        $this->serializer = $serializer;
        $this->platform = $platform;
    }

    /**
     * @return string
     */
    public function get(): string
    {
        // Use pre-generated version if available
        $userAgent = $this->cache->load(self::RVVUP_USER_AGENT_STRING);
        if ($userAgent) {
            return $userAgent;
        }

        // Attempt to figure out what plugin version we have depending on installation method
        $packages = $this->composerInformation->getInstalledMagentoPackages();
        $moduleVersion = $packages['rvvup/module-magento-payments']['version']
            ?? $this->loadAppCodeComposerJsonVersion();

        // Build result
        $parts = [
            'RvvupMagentoPayments/' . $moduleVersion,
            $this->platform->getName() . '-' . $this->platform->getEdition() . '/' . $this->platform->getVersion(),
            'PHP/' . phpversion(),
        ];
        $userAgent = implode("; ", $parts);

        // Save to cache and return
        $this->cache->save($userAgent, self::RVVUP_USER_AGENT_STRING);
        return $userAgent;
    }

    /**
     * @return string
     */
    private function loadAppCodeComposerJsonVersion(): string
    {
        $fileName = __DIR__ . '/composer.json';
        if (!$this->fileIo->fileExists($fileName)) {
            return 'unknown';
        }
        try {
            $composerFile = $this->fileIo->read($fileName);
            if (!$composerFile) {
                return 'unknown';
            }
            $composerData = $this->serializer->unserialize($composerFile);
            if (isset($composerData['version'])) {
                return (string) $composerData['version'];
            }
            return 'unknown';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }
}
