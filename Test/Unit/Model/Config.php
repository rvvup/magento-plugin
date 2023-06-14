<?php

declare(strict_types=1);

namespace Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Model\ConfigInterface;

class Config extends TestCase
{
    protected function setUp(): void
    {
        $this->scopeConfigMock = $this->getMockBuilder(ScopeConfigInterface::class)
            ->disableOriginalConstructor()->getMock();

        $this->config = new \Rvvup\Payments\Model\Config($this->scopeConfigMock);
    }

    public function testPaypalBlockDefaultStyling()
    {
        $testedValue = 'value';
        $config = ConfigInterface::XML_PATH_STYLE;

        $basePath = ConfigInterface::RVVUP_CONFIG . ConfigInterface::XML_PATH_PAYPAL_BLOCK;

        $this->scopeConfigMock->method('getValue')->withConsecutive(
            [$basePath . ConfigInterface::XML_PATH_USE_PLACE_ORDER_STYLING, ScopeInterface::SCOPE_STORE, null],
            [$basePath . ConfigInterface::XML_PATH_PLACE_ORDER_STYLING, ScopeInterface::SCOPE_STORE, null]
        )
            ->willReturnOnConsecutiveCalls(
                '1',
                $testedValue,
            );

        $this->assertEquals($testedValue, $this->config->getPaypalBlockStyling($config));
    }

    public function testPaypalBlockUsePlaceOrderStyling()
    {
        $testedValue = 'value';
        $config = ConfigInterface::XML_PATH_STYLE;

        $basePath = ConfigInterface::RVVUP_CONFIG . ConfigInterface::XML_PATH_PAYPAL_BLOCK;

        $this->scopeConfigMock->method('getValue')->withConsecutive(
            [$basePath . ConfigInterface::XML_PATH_USE_PLACE_ORDER_STYLING, ScopeInterface::SCOPE_STORE, null],
            [$basePath . ConfigInterface::XML_PATH_STYLE, ScopeInterface::SCOPE_STORE, null]
        )
            ->willReturnOnConsecutiveCalls(
                '0',
                $testedValue,
            );

        $this->assertEquals($testedValue, $this->config->getPaypalBlockStyling($config));
    }


    public function testPaypalBlockPropertiesValue()
    {
        $testedValue = 'value';
        $config = 'some_config_value';

        $basePath = ConfigInterface::RVVUP_CONFIG . ConfigInterface::XML_PATH_PAYPAL_BLOCK;

        $this->scopeConfigMock->method('getValue')->withConsecutive(
            [$basePath . $config, ScopeInterface::SCOPE_STORE, null]
        )
            ->willReturnOnConsecutiveCalls(
                $testedValue,
            );
        $this->assertEquals($testedValue, $this->config->getPaypalBlockStyling($config));
    }
}
