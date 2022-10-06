<?php declare(strict_types=1);

namespace Rvvup\Payments\Plugin;

use Magento\Checkout\Block\Checkout\LayoutProcessor;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\SdkProxy;

class JsLayout
{
    /** @var ConfigInterface */
    private $config;
    /** @var SdkProxy */
    private $sdkProxy;

    /**
     * @param ConfigInterface $config
     * @param SdkProxy $sdkProxy
     * @return void
     */
    public function __construct(ConfigInterface $config, SdkProxy $sdkProxy)
    {
        $this->config = $config;
        $this->sdkProxy = $sdkProxy;
    }

    /**
     * @param LayoutProcessor $subject
     * @param array $jsLayout
     * @return array
     */
    public function beforeProcess(LayoutProcessor $subject, $jsLayout)
    {
        if ($this->config->isActive()) {
            $renders = &$jsLayout["components"]["checkout"]["children"]["steps"]["children"]["billing-step"]["children"]
            ["payment"]["children"]["renders"]["children"];

            $renders = array_merge($renders, $this->getRvvupMethods());
        }
        return [$jsLayout];
    }

    /**
     * @return array
     */
    private function getRvvupMethods(): array
    {
        $template = ['component' => 'Rvvup_Payments/js/view/payment/rvvup'];
        $loadedMethods = $this->sdkProxy->getMethods('0', 'GBP');
        $template['methods'] = [];
        foreach ($loadedMethods as $method) {
            $template['methods']['rvvup_' . $method['name']] = ['isBillingAddressRequired' => true];
        }
        return ['rvvup' => $template];
    }
}
