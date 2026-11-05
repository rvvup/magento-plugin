<?php declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Model;

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Rvvup\Payments\Model\Config\RvvupConfigurationInterface;
use Rvvup\Payments\Model\Environment\GetEnvironmentVersionsInterface;
use Rvvup\Payments\Model\SdkProxy;
use Rvvup\Payments\Model\UserAgentBuilder;
use Rvvup\Sdk\GraphQlSdk;
use Rvvup\Sdk\GraphQlSdkFactory;

/**
 * @covers \Rvvup\Payments\Model\SdkProxy
 */
class SdkProxyTest extends TestCase
{
    private const METHODS = [['name' => 'PAYPAL'], ['name' => 'CLEARPAY']];

    /** @var GraphQlSdk|MockObject */
    private $sdkMock;
    /** @var LoggerInterface|MockObject */
    private $loggerMock;
    /** @var SdkProxy */
    private $sdkProxy;

    protected function setUp(): void
    {
        $this->sdkMock = $this->getMockBuilder(GraphQlSdk::class)->disableOriginalConstructor()->getMock();
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $sdkFactoryMock = $this->getMockBuilder(GraphQlSdkFactory::class)->disableOriginalConstructor()->getMock();
        $sdkFactoryMock->method('create')->willReturn($this->sdkMock);

        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getId')->willReturn(1);
        $storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $storeManagerMock->method('getStore')->willReturn($storeMock);

        $userAgentMock = $this->getMockBuilder(UserAgentBuilder::class)->disableOriginalConstructor()->getMock();
        $userAgentMock->method('get')->willReturn('RvvupMagentoPayments/test');

        $this->sdkProxy = new SdkProxy(
            $this->createMock(RvvupConfigurationInterface::class),
            $userAgentMock,
            $sdkFactoryMock,
            $storeManagerMock,
            $this->createMock(GetEnvironmentVersionsInterface::class),
            $this->loggerMock
        );
    }

    public function testItSkipsFurtherTimeBoundedLookupsAfterOneTimesOut(): void
    {
        $calls = 0;
        $this->sdkMock->method('getMethods')->willReturnCallback(function () use (&$calls) {
            $calls++;
            // The SDK swallows request errors, so a timeout looks like an empty list after waiting.
            usleep(60000);

            return [];
        });
        $this->loggerMock->expects($this->once())->method('warning');

        $this->assertSame([], $this->sdkProxy->getMethods('0', 'GBP', $this->boundedOptions()));
        $this->assertSame([], $this->sdkProxy->getMethods('49.99', 'GBP', $this->boundedOptions()));
        $this->assertSame([], $this->sdkProxy->getMethods('10.00', 'GBP', $this->boundedOptions()));

        $this->assertSame(1, $calls);
    }

    public function testItStillAsksTheApiForLookupsWithoutATimeoutAfterABoundedOneTimedOut(): void
    {
        $this->sdkMock->method('getMethods')->willReturnCallback(function ($value, $currency, $options) {
            if ($options !== null) {
                usleep(60000);

                return [];
            }

            return self::METHODS;
        });

        $this->assertSame([], $this->sdkProxy->getMethods('0', 'GBP', $this->boundedOptions()));
        $this->assertCount(2, $this->sdkProxy->getMethods('0', 'GBP'));
    }

    public function testAFastEmptyAnswerIsRememberedAndDoesNotStopOtherBoundedLookups(): void
    {
        $this->sdkMock->expects($this->exactly(2))->method('getMethods')->willReturn([]);
        $this->loggerMock->expects($this->never())->method('warning');

        $this->assertSame([], $this->sdkProxy->getMethods('0', 'GBP', $this->generousOptions()));
        // Same lookup again is answered from memory.
        $this->assertSame([], $this->sdkProxy->getMethods('0', 'GBP', $this->generousOptions()));
        // A different amount is a new lookup and still goes to the API.
        $this->assertSame([], $this->sdkProxy->getMethods('49.99', 'GBP', $this->generousOptions()));
    }

    public function testASuccessfulTimeBoundedLookupIsRemembered(): void
    {
        $this->sdkMock->expects($this->once())->method('getMethods')->willReturn(self::METHODS);

        $this->assertCount(2, $this->sdkProxy->getMethods('0', 'GBP', $this->generousOptions()));
        $this->assertCount(2, $this->sdkProxy->getMethods('0', 'GBP', $this->generousOptions()));
    }

    public function testItPassesTheTimeoutOptionsToTheSdk(): void
    {
        $options = $this->generousOptions();

        $this->sdkMock->expects($this->once())
            ->method('getMethods')
            ->with('0', 'GBP', $options)
            ->willReturn(self::METHODS);

        $this->sdkProxy->getMethods('0', 'GBP', $options);
    }

    public function testLookupsWithoutOptionsAreNeverTreatedAsTimedOut(): void
    {
        $calls = 0;
        $this->sdkMock->method('getMethods')->willReturnCallback(function () use (&$calls) {
            $calls++;
            usleep(60000);

            return [];
        });
        $this->loggerMock->expects($this->never())->method('warning');

        $this->sdkProxy->getMethods('0', 'GBP');
        $this->sdkProxy->getMethods('49.99', 'GBP');

        $this->assertSame(2, $calls);
    }

    public function testCleanResetsTheTimedOutState(): void
    {
        $calls = 0;
        $this->sdkMock->method('getMethods')->willReturnCallback(function () use (&$calls) {
            $calls++;
            usleep(60000);

            return [];
        });

        $this->sdkProxy->getMethods('0', 'GBP', $this->boundedOptions());
        $this->sdkProxy->clean();
        $this->sdkProxy->getMethods('0', 'GBP', $this->boundedOptions());

        $this->assertSame(2, $calls);
    }

    /**
     * Tiny timeouts, so the mocked lookups that sleep for 60ms count as having timed out. The proxy only compares
     * these values with the time the lookup took.
     *
     * @return array
     */
    private function boundedOptions(): array
    {
        return ['timeout' => 0.03, 'connect_timeout' => 0.02];
    }

    /**
     * Timeouts far longer than any mocked lookup takes, so nothing counts as having timed out.
     *
     * @return array
     */
    private function generousOptions(): array
    {
        return ['timeout' => 5.0, 'connect_timeout' => 2.0];
    }
}
