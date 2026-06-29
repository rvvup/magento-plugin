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

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->phpVersion = phpversion();
        $this->cacheMock = $this->getMockBuilder(CacheInterface::class)->disableOriginalConstructor()->getMock();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->phpVersion = null;
        $this->cacheMock = null;
    }

    /**
     * @param array $versions
     * @return UserAgentBuilder
     */
    private function createSystemUnderTest(array $versions = []): UserAgentBuilder
    {
        $versions = array_merge($this->defaultVersions(), $versions);

        $getEnvironmentVersionsMock = $this->getMockBuilder(GetEnvironmentVersionsInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $getEnvironmentVersionsMock->expects($this->once())
            ->method('execute')
            ->willReturn($versions);

        return new UserAgentBuilder($this->cacheMock, $getEnvironmentVersionsMock);
    }

    /**
     * @return array
     */
    private function defaultVersions(): array
    {
        return [
            'rvvp_module_version' => '0.1.0',
            'rvvp_hyva_checkout_module_version' => '',
            'php_version' => $this->phpVersion,
            'magento_version' => [
                'name' => 'Magento',
                'edition' => 'Community',
                'version' => '2.4.4'
            ]
        ];
    }

    public function testEverythingIsWorking(): void
    {
        $systemUnderTest = $this->createSystemUnderTest();

        $this->assertEquals(
            'RvvupMagentoPayments/0.1.0; Magento-Community/2.4.4; PHP/' . $this->phpVersion,
            $systemUnderTest->get(),
            "Unexpected value when testing composer-based install UA string"
        );
    }

    public function testHyvaCheckoutModuleInstalled(): void
    {
        $systemUnderTest = $this->createSystemUnderTest([
            'rvvp_hyva_checkout_module_version' => '0.2.0',
        ]);

        $this->assertEquals(
            'RvvupMagentoPayments/0.1.0; RvvupMagentoPaymentsHyvaCheckout/0.2.0; Magento-Community/2.4.4; PHP/' . $this->phpVersion,
            $systemUnderTest->get(),
            'UA string should include hyva checkout segment when module is installed'
        );
    }

    public function testGeneratedStringIsRetrievedFromCache(): void
    {
        $uaString = 'RvvupMagentoPayments/0.1.0; Magento-Community/2.4.4; PHP/' . $this->phpVersion;
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

        $systemUnderTest = $this->createSystemUnderTest();

        $this->assertEquals(
            $uaString,
            $systemUnderTest->get(),
            'Cache saving/loading has unexpected behaviour'
        );
        $this->assertEquals(
            $uaString,
            $systemUnderTest->get(),
            'Cache saving/loading has unexpected behaviour'
        );
    }
}
