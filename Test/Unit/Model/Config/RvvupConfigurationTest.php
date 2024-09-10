<?php

namespace Rvvup\Payments\Test\Unit\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Model\Config\RvvupConfiguration;

class RvvupConfigurationTest extends TestCase
{
    private $scopeConfigMock;
    private $rvvupConfiguration;

    private const TEST_JWT = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhdWQiOiJodHRwczovL2FwaS5ydnZ1cC5jb20vZ3JhcGhxbCIsImlhdCI6MTcyNTk1NjkwMSwibGl2ZSI6dHJ1ZSwiZGFzaGJvYXJkVXJsIjoiaHR0cHM6Ly9kYXNoYm9hcmQucnZ2dXAuY29tIiwibWVyY2hhbnRJZCI6Ik1FMDFKN0RHVEVUWFk2MjJUU1ZDWkpXTU5HSE4iLCJ1c2VybmFtZSI6InJ2dnVwVXNlcm5tYWUiLCJwYXNzd29yZCI6InJ2dnVwUGFzc3dvcmQifQ.faketest";
    protected function setUp(): void
    {
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->rvvupConfiguration = new RvvupConfiguration($this->scopeConfigMock);
    }

    public function testGetMerchantId()
    {
        $this->scopeConfigMock->method("getValue")->with("payment/rvvup/jwt", ScopeInterface::SCOPE_STORE, 1)
            ->willReturn(self::TEST_JWT);
        $this->assertEquals('ME01J7DGTETXY622TSVCZJWMNGHN', $this->rvvupConfiguration->getMerchantId(1));
    }
}
