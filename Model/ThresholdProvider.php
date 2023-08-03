<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Framework\Exception\LocalizedException;

class ThresholdProvider
{
    /** @var array[] */
    private $thresholds = [];
    /** @var SdkProxy */
    private $sdkProxy;

    /**
     * @param SdkProxy $sdkProxy
     */
    public function __construct(
        SdkProxy $sdkProxy
    ) {
        $this->sdkProxy = $sdkProxy;
    }

    /**
     * @param string $provider
     * @param string|null $currency
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function get(string $provider, string $currency = null): array
    {
        if (!$this->thresholds) {
            $this->init($currency);
        }
        if (isset($this->thresholds[$provider])) {
            return $this->thresholds[$provider];
        }

        return [];
    }

    /**
     * @param string|null $currency
     * @return void
     */
    private function init(string $currency = null): void
    {
        $methods = $this->sdkProxy->getMethods('0', $currency ?? 'GBP');
        foreach ($methods as $method) {
            $this->thresholds[$method['name']] = [];
            if (isset($method['limits']['total'])) {
                foreach ($method['limits']['total'] as $limit) {
                    $this->thresholds[$method['name']][$limit['currency']] = [
                        'min' => $limit['min'],
                        'max' => $limit['max'],
                    ];
                }
            }
        }
    }
}
