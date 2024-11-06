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

    private const TEST_JWT = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhdWQiOiJodHRwczovL2FwaS5ydnZ1cC5jb20vZ3JhcGhxbCIsImlhdCI6MTcyNTk1NjkwMSwibGl2ZSI6dHJ1ZSwiZGFzaGJvYXJkVXJsIjoiaHR0cHM6Ly9kYXNoYm9hcmQucnZ2dXAuY29tIiwibWVyY2hhbnRJZCI6Ik1FMDFKN0RHVEVUWFk2MjJUU1ZDWkpXTU5HSE4iLCJ1c2VybmFtZSI6InJ2dnVwVXNlcm5hbWUiLCJwYXNzd29yZCI6InJ2dnVwUGFzc3dvcmQifQ.fakeTest";
    protected function setUp(): void
    {
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->rvvupConfiguration = new RvvupConfiguration($this->scopeConfigMock);
    }

    public function testGetMerchantId()
    {
        $this->scopeConfigMock->method("getValue")->with("payment/rvvup/jwt", ScopeInterface::SCOPE_STORE, "1")
            ->willReturn(self::TEST_JWT);
        $this->assertEquals('ME01J7DGTETXY622TSVCZJWMNGHN', $this->rvvupConfiguration->getMerchantId("1"));
    }
    public function testGetMerchantIdReturnsNullIfJwtDoesNotContainPayload()
    {
        $this->scopeConfigMock->method("getValue")->with("payment/rvvup/jwt", ScopeInterface::SCOPE_STORE, "1")
            ->willReturn("test");
        $this->assertEquals(null, $this->rvvupConfiguration->getMerchantId("1"));
    }
    public function testGetMerchantIdReturnsNull()
    {
        $this->scopeConfigMock->method("getValue")->with("payment/rvvup/jwt", ScopeInterface::SCOPE_STORE, "1")
            ->willReturn(null);
        $this->assertEquals(null, $this->rvvupConfiguration->getMerchantId("1"));
    }
    public function testGetJsSdkUrl()
    {
        $this->scopeConfigMock->method("getValue")->with("payment/rvvup/jwt", ScopeInterface::SCOPE_STORE, "1")
            ->willReturn(self::TEST_JWT);

        $this->assertEquals('https://checkout.rvvup.com/sdk/v1-unstable.js', $this->rvvupConfiguration->getJsSdkUrl("1"));
    }
    public function testGetJsSdkUrlReturnsNull()
    {
        $this->scopeConfigMock->method("getValue")->with("payment/rvvup/jwt", ScopeInterface::SCOPE_STORE, "1")
            ->willReturn(null);
        $this->assertEquals(null, $this->rvvupConfiguration->getJsSdkUrl("1"));
    }
    public function testGetRestApiUrl()
    {
        $this->scopeConfigMock->method("getValue")->with("payment/rvvup/jwt", ScopeInterface::SCOPE_STORE, "1")
            ->willReturn(self::TEST_JWT);

        $this->assertEquals('https://api.rvvup.com/api/2024-03-01', $this->rvvupConfiguration->getRestApiUrl("1"));
    }
    public function testGetRestApiUrlReturnsNull()
    {
        $this->scopeConfigMock->method("getValue")->with("payment/rvvup/jwt", ScopeInterface::SCOPE_STORE, "1")
            ->willReturn(null);
        $this->assertEquals(null, $this->rvvupConfiguration->getRestApiUrl("1"));
    }

    public function testGetGraphQlUrl()
    {
        $this->scopeConfigMock->method("getValue")->with("payment/rvvup/jwt", ScopeInterface::SCOPE_STORE, "1")
            ->willReturn(self::TEST_JWT);

        $this->assertEquals('https://api.rvvup.com/graphql', $this->rvvupConfiguration->getGraphQlUrl("1"));
    }
    public function testGetGraphQlUrlReturnsNull()
    {
        $this->scopeConfigMock->method("getValue")->with("payment/rvvup/jwt", ScopeInterface::SCOPE_STORE, "1")
            ->willReturn(null);
        $this->assertEquals(null, $this->rvvupConfiguration->getGraphQlUrl("1"));
    }

    public function testGetBearerTokenReturnsNullIfNotString()
    {
        $this->scopeConfigMock->method("getValue")->with("payment/rvvup/jwt", ScopeInterface::SCOPE_STORE, "1")
            ->willReturn(123);
        $this->assertEquals(null, $this->rvvupConfiguration->getBearerToken("1"));
    }

    public function testGetBearerTokenReturnsTrimmedToken()
    {
        $this->scopeConfigMock->method("getValue")->with("payment/rvvup/jwt", ScopeInterface::SCOPE_STORE, "1")
            ->willReturn("  " . self::TEST_JWT . "  ");
        $this->assertEquals(self::TEST_JWT, $this->rvvupConfiguration->getBearerToken("1"));
    }


    public function testGetBearerToken()
    {
        $this->scopeConfigMock->method("getValue")->with("payment/rvvup/jwt", ScopeInterface::SCOPE_STORE, "1")
            ->willReturn(self::TEST_JWT);
        $this->assertEquals(self::TEST_JWT, $this->rvvupConfiguration->getBearerToken("1"));
    }


    public function testGetBasicAuthToken()
    {
        $this->scopeConfigMock->method("getValue")->with("payment/rvvup/jwt", ScopeInterface::SCOPE_STORE, "1")
            ->willReturn(self::TEST_JWT);

        $this->assertEquals('cnZ2dXBVc2VybmFtZTpydnZ1cFBhc3N3b3Jk', $this->rvvupConfiguration->getBasicAuthToken("1"));
    }

}
