<?php declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Model;

use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Model\Environment\GetEnvironmentVersionsInterface;
use Rvvup\Payments\Model\UserAgentBuilder;

/**
 * @covers \Rvvup\Payments\Model\UserAgentBuilder
 */
class UserAgentBuilderTest extends TestCase
{
    /** @var string */
    private $phpVersion;
    /** @var CacheInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $cacheMock;
    /** @var UserAgentBuilder */
    private $systemUnderTest;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->phpVersion = phpversion();
        $this->cacheMock = $this->getMockBuilder(CacheInterface::class)->disableOriginalConstructor()->getMock();

        $getEnvironmentVersionsMock = $this->getMockBuilder(GetEnvironmentVersionsInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $getEnvironmentVersionsMock->expects($this->once())
            ->method('execute')
            ->willReturn([
                'rvvp_module_version' => '0.1.0',
                'php_version' => $this->phpVersion,
                'magento_version' => [
                    'name' => 'Magento',
                    'edition' => 'Community',
                    'version' => '2.4.4'
                ]
            ]);

        $this->systemUnderTest = new UserAgentBuilder($this->cacheMock, $getEnvironmentVersionsMock);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->phpVersion = null;
        $this->cacheMock = null;
        $this->systemUnderTest = null;
    }

    public function testEverythingIsWorking(): void
    {
        $this->assertEquals(
            'RvvupMagentoPayments/0.1.0; Magento-Community/2.4.4; PHP/' . $this->phpVersion,
            $this->systemUnderTest->get(),
            "Unexpected value when testing composer-based install UA string"
        );
    }

    public function testGeneratedStringIsRetrievedFromCache(): void
    {
        $uaString =  'RvvupMagentoPayments/0.1.0; Magento-Community/2.4.4; PHP/' . $this->phpVersion;
        $this->cacheMock
            ->expects($this->exactly(2))
            ->method('load')
            ->with(UserAgentBuilder::RVVUP_USER_AGENT_STRING)
            ->willReturnOnConsecutiveCalls(
                false,
                $uaString
            );

        $this->cacheMock->expects($this->once())->method('save')->with(
            $uaString,
            UserAgentBuilder::RVVUP_USER_AGENT_STRING
        );

        $this->assertEquals(
            $uaString,
            $this->systemUnderTest->get(),
            'Cache saving/loading has unexpected behaviour'
        );
        $this->assertEquals(
            $uaString,
            $this->systemUnderTest->get(),
            'Cache saving/loading has unexpected behaviour'
        );
    }
}
