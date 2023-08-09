<?php
declare(strict_types=1);

namespace Rvvup\Payments\Model\Clearpay;

use Rvvup\Payments\Model\SdkProxy;
use Rvvup\Payments\ViewModel\Clearpay;

class Config
{

    /**
     * @var SdkProxy
     */
    private $sdkProxy;

    /**
     * @var string
     */
    private $settings;

    /**
     * @param SdkProxy $sdkProxy
     */
    public function __construct(
        SdkProxy $sdkProxy
    ) {
        $this->sdkProxy = $sdkProxy;
    }

    /**
     * @param string $area
     * @return bool
     */
    public function isEnabled(string $area): bool
    {
        $settings = $this->getClearPayMethodSettings($area);
        return $settings && (bool)$settings['messaging']['enabled'];
    }

    /**
     * @param string $area
     * @return string
     */
    public function getTheme(string $area): string
    {
        $settings = $this->getClearPayMethodSettings($area);
        return $settings ? (string)$settings['theme']['value'] : "";
    }

    /**
     * @param string $area
     * @return string
     */
    public function getIconType(string $area): string
    {
        $settings = $this->getClearPayMethodSettings($area);
        return $settings ? (string)$settings['messaging']['iconType']['value'] : "";
    }

    /**
     * @param string $area
     * @return string
     */
    public function getModalTheme(string $area): string
    {
        $settings = $this->getClearPayMethodSettings($area);
        return $settings ? (string)$settings['messaging']['modalTheme']['value'] : '';
    }

    /**
     * @param string $area
     * @return array
     */
    private function getClearPayMethodSettings(string $area): array
    {
        if (!isset($this->settings[$area])) {
            foreach ($this->sdkProxy->getMethods() as $method) {
                if ($method['name'] == Clearpay::PROVIDER) {
                    $result = $method['settings'][$area];
                    $this->settings[$area] = $result;
                    return $result;
                }
            }
            return [];
        }
        return $this->settings[$area];
    }
}
