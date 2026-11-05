<?php declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Model;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\PaymentMethodsAvailableGet;
use Rvvup\Payments\Model\SdkProxy;
use Rvvup\Payments\Model\StorefrontRequestOptions;

/**
 * @covers \Rvvup\Payments\Model\PaymentMethodsAvailableGet
 */
class PaymentMethodsAvailableGetTest extends TestCase
{
    /** @var SdkProxy|MockObject */
    private $sdkProxyMock;
    /** @var LoggerInterface|MockObject */
    private $loggerMock;

    protected function setUp(): void
    {
        $this->sdkProxyMock = $this->getMockBuilder(SdkProxy::class)->disableOriginalConstructor()->getMock();
        $this->loggerMock = $this->createMock(LoggerInterface::class);
    }

    public function testItDoesNotTimeBoundTheLookupByDefault(): void
    {
        $this->sdkProxyMock->expects($this->once())
            ->method('getMethods')
            ->with('10.00', 'GBP', null)
            ->willReturn([['name' => 'PAYPAL']]);

        $result = (new PaymentMethodsAvailableGet($this->sdkProxyMock, $this->loggerMock))
            ->execute('10.00', 'GBP');

        $this->assertArrayHasKey('rvvup_paypal', $result);
    }

    public function testItTimeBoundsTheLookupWhenStorefrontOptionsAreInjected(): void
    {
        $this->sdkProxyMock->expects($this->once())
            ->method('getMethods')
            ->with('0', 'GBP', ['timeout' => 2.5, 'connect_timeout' => 0.5])
            ->willReturn([['name' => 'CLEARPAY']]);

        $result = (new PaymentMethodsAvailableGet(
            $this->sdkProxyMock,
            $this->loggerMock,
            new StorefrontRequestOptions(2.5, 0.5)
        ))->execute('0', 'GBP');

        $this->assertArrayHasKey('rvvup_clearpay', $result);
    }

    public function testItReturnsNoMethodsAndLogsWhenTheLookupThrows(): void
    {
        $this->sdkProxyMock->method('getMethods')->willThrowException(new \RuntimeException('boom'));
        $this->loggerMock->expects($this->once())->method('error');

        $this->assertSame(
            [],
            (new PaymentMethodsAvailableGet($this->sdkProxyMock, $this->loggerMock))->execute('0', 'GBP')
        );
    }
}
