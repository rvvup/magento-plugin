<?php

declare(strict_types=1);

namespace Rvvup\Payments\Plugin;

use Magento\Payment\Helper\Data;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\RvvupConfigProvider;
use Rvvup\Payments\Model\SdkProxy;

class PaymentMethod
{
    private const VALUE = 'value';

    /**
     * @var SdkProxy
     */
    private $sdkProxy;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @param SdkProxy $sdkProxy
     * @param ConfigInterface $config
     */
    public function __construct(
        SdkProxy $sdkProxy,
        ConfigInterface $config
    ) {
        $this->sdkProxy = $sdkProxy;
        $this->config = $config;
    }

    /**
     * @param Data $subject
     * @param array $result
     * @param bool $sorted
     * @param bool $asLabelValue
     * @param bool $withGroups
     * @param Store|null $store
     * @return array
     */
    public function afterGetPaymentMethodList(
        Data $subject,
        array $result,
        $sorted = true,
        $asLabelValue = false,
        $withGroups = false,
        $store = null
    ): array {
        if (!$this->config->isActive()) {
            return $result;
        }

        if (isset($result[RvvupConfigProvider::CODE])) {
            $result = array_merge($result, $this->getMethods());
            unset($result[RvvupConfigProvider::CODE]);
        } elseif (isset($result[RvvupConfigProvider::GROUP_CODE])) {
            $result[RvvupConfigProvider::GROUP_CODE][self::VALUE] = $this->getMethods();
            unset($result[RvvupConfigProvider::CODE][self::VALUE][RvvupConfigProvider::CODE]);
        }

        return $result;
    }
    private function getMethods(): array
    {
        $result = [];

        foreach ($this->sdkProxy->getMethods() ?? [] as $method) {
            $code = RvvupConfigProvider::CODE . '_' . $method['name'];
            $result[$code] = [
                self::VALUE => $code,
                'label' => 'Rvvup ' . $method['displayName']
            ];
        }
        return $result;
    }
}
