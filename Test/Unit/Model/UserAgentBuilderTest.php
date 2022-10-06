<?php declare(strict_types=1);

namespace Rvvup\Sdk\Test\Unit;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Composer\ComposerInformation;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Model\UserAgentBuilder;

/**
 * @covers \Rvvup\Payments\Model\UserAgentBuilder
 */
class UserAgentStringTest extends TestCase
{
    /** @var string */
    private $phpVersion;
    /** @var CacheInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $cacheMock;
    /** @var File|\PHPUnit\Framework\MockObject\MockObject */
    private $fileIoMock;
    /** @var SerializerInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $serializerMock;
    /** @var ComposerInformation|\PHPUnit\Framework\MockObject\MockObject */
    private $composerInfoMock;
    /** @var UserAgentBuilder */
    private $systemUnderTest;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->phpVersion = phpversion();
        $this->cacheMock = $this->getMockBuilder(CacheInterface::class)->disableOriginalConstructor()->getMock();
        $this->composerInfoMock = $this->getMockBuilder(ComposerInformation::class)->disableOriginalConstructor()->getMock();
        $this->fileIoMock = $this->getMockBuilder(File::class)->disableOriginalConstructor()->getMock();
        $this->serializerMock = $this->getMockBuilder(SerializerInterface::class)->disableOriginalConstructor()->getMock();
        $productMetadataMock = $this->getMockBuilder(ProductMetadataInterface::class)->disableOriginalConstructor()->getMock();
        $productMetadataMock->expects($this->once())->method('getName')->willReturn('Magento');
        $productMetadataMock->expects($this->once())->method('getEdition')->willReturn('Community');
        $productMetadataMock->expects($this->once())->method('getVersion')->willReturn('2.4.4');
        $this->systemUnderTest = new UserAgentBuilder(
            $this->cacheMock,
            $this->composerInfoMock,
            $this->fileIoMock,
            $this->serializerMock,
            $productMetadataMock
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->phpVersion = null;
        $this->cacheMock = null;
        $this->composerInfoMock = null;
        $this->fileIoMock = null;
        $this->serializerMock = null;
        $this->systemUnderTest = null;
    }

    public function testEverythingIsWorkingInstalledViaComposer()
    {
        $this->composerInfoMock->expects($this->once())->method('getInstalledMagentoPackages')->willReturn([
            'rvvup/module-magento-payments' => [
                'version' => '0.1.0'
            ],
        ]);
        $this->assertEquals(
            'RvvupMagentoPayments/0.1.0; Magento-Community/2.4.4; PHP/' . $this->phpVersion,
            $this->systemUnderTest->get(),
            "Unexpected value when testing composer-based install UA string"
        );
    }

    public function testEverythingIsWorkingInstalledInAppCode()
    {
        $this->composerInfoMock->expects($this->once())->method('getInstalledMagentoPackages')->willReturn([]);
        $path = dirname((new \ReflectionClass(UserAgentBuilder::class))->getFileName()) . '/composer.json';
        $this->fileIoMock->expects($this->once())->method('fileExists')->with($path)->willReturn(true);
        $this->fileIoMock->expects($this->once())->method('read')->with($path)->willReturn('{"version": "0.0.1"}');
        $this->serializerMock->expects($this->once())->method('unserialize')->willReturn(['version' => '0.1.0']);

        $this->assertEquals(
            'RvvupMagentoPayments/0.1.0; Magento-Community/2.4.4; PHP/' . $this->phpVersion,
            $this->systemUnderTest->get(),
            "Unexpected value when testing app/code-based install UA string"
        );
    }

    public function testComposerJsonMissingVersionInAppCode()
    {
        $this->composerInfoMock->expects($this->once())->method('getInstalledMagentoPackages')->willReturn([]);
        $path = dirname((new \ReflectionClass(UserAgentBuilder::class))->getFileName()) . '/composer.json';
        $this->fileIoMock->expects($this->once())->method('fileExists')->with($path)->willReturn(true);
        $this->fileIoMock->expects($this->once())->method('read')->with($path)->willReturn('{}');
        $this->serializerMock->expects($this->once())->method('unserialize')->willReturn([]);

        $this->assertEquals(
            'RvvupMagentoPayments/unknown; Magento-Community/2.4.4; PHP/' . $this->phpVersion,
            $this->systemUnderTest->get(),
            "Unexpected value when testing missing composer file UA string"
        );
    }

    public function testCorruptComposerJsonInAppCode()
    {
        $this->composerInfoMock->expects($this->once())->method('getInstalledMagentoPackages')->willReturn([]);
        $path = dirname((new \ReflectionClass(UserAgentBuilder::class))->getFileName()) . '/composer.json';
        $this->fileIoMock->expects($this->once())->method('fileExists')->with($path)->willReturn(true);
        $this->fileIoMock->expects($this->once())->method('read')->with($path)->willReturn('some currput data');
        $this->serializerMock->expects($this->once())->method('unserialize')->willThrowException(new \InvalidArgumentException());

        $this->assertEquals(
            'RvvupMagentoPayments/unknown; Magento-Community/2.4.4; PHP/' . $this->phpVersion,
            $this->systemUnderTest->get(),
            "Unexpected value when testing corrupt composer file fallback"
        );
    }

    public function testEmptyComposerJsonInAppCode()
    {
        $this->composerInfoMock->expects($this->once())->method('getInstalledMagentoPackages')->willReturn([]);
        $path = dirname((new \ReflectionClass(UserAgentBuilder::class))->getFileName()) . '/composer.json';
        $this->fileIoMock->expects($this->once())->method('fileExists')->with($path)->willReturn(true);
        $this->fileIoMock->expects($this->once())->method('read')->with($path)->willReturn('');
        $this->assertEquals(
            'RvvupMagentoPayments/unknown; Magento-Community/2.4.4; PHP/' . $this->phpVersion,
            $this->systemUnderTest->get(),
            "Unexpected value when testing corrupt composer file fallback"
        );
    }

    public function testSuccessfulFallbackIfUnableToLocateVersion()
    {
        $this->composerInfoMock->expects($this->once())->method('getInstalledMagentoPackages')->willReturn([]);
        $this->composerInfoMock->expects($this->once())->method('getInstalledMagentoPackages')->willReturn([]);
        $path = dirname((new \ReflectionClass(UserAgentBuilder::class))->getFileName()) . '/composer.json';
        $this->fileIoMock->expects($this->once())->method('fileExists')->with($path)->willReturn(false);
        $this->assertEquals(
            'RvvupMagentoPayments/unknown; Magento-Community/2.4.4; PHP/' . $this->phpVersion,
            $this->systemUnderTest->get(),
            "Unexpected value when testing missing composer version key fallback"
        );
    }

    public function testGeneratedStringIsRetrievedFromCache()
    {
        $uaString =  'RvvupMagentoPayments/0.1.0; Magento-Community/2.4.4; PHP/' . $this->phpVersion;
        $this->cacheMock->expects($this->exactly(2))
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
        $this->composerInfoMock->expects($this->once())->method('getInstalledMagentoPackages')->willReturn([
            'rvvup/module-magento-payments' => [
                'version' => '0.1.0'
            ],
        ]);
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
