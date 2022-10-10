<?php

declare(strict_types=1);

namespace Rvvup\Payments\Test\Unit\Model\Environment;

use InvalidArgumentException;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Composer\ComposerInformation;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Rvvup\Payments\Model\Environment\GetEnvironmentVersions;

/**
 * @covers \Rvvup\Payments\Model\Environment\GetEnvironmentVersions
 */
class GetEnvironmentVersionsTest extends TestCase
{
    /**
     * @var false|string
     */
    private $phpVersion;

    /**
     * @var \Magento\Framework\App\CacheInterface&\PHPUnit\Framework\MockObject\MockObject|\PHPUnit\Framework\MockObject\MockObject
     */
    private $cacheMock;

    /**
     * @var \Magento\Framework\Filesystem\Io\File&\PHPUnit\Framework\MockObject\MockObject|\PHPUnit\Framework\MockObject\MockObject
     */
    private $fileIoMock;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface&\PHPUnit\Framework\MockObject\MockObject|\PHPUnit\Framework\MockObject\MockObject
     */
    private $serializerMock;

    /**
     * @var \Magento\Framework\Composer\ComposerInformation&\PHPUnit\Framework\MockObject\MockObject|\PHPUnit\Framework\MockObject\MockObject
     */
    private $composerInfoMock;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|\Psr\Log\LoggerInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $loggerMock;

    /**
     * @var \Rvvup\Payments\Model\Environment\GetEnvironmentVersions
     */
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
        $this->loggerMock = $this->getMockBuilder(LoggerInterface::class)->disableOriginalConstructor()->getMock();

        $productMetadataMock = $this->getMockBuilder(ProductMetadataInterface::class)->disableOriginalConstructor()->getMock();
        $productMetadataMock->expects($this->once())->method('getName')->willReturn('Magento');
        $productMetadataMock->expects($this->once())->method('getEdition')->willReturn('Community');
        $productMetadataMock->expects($this->once())->method('getVersion')->willReturn('2.4.4');

        $this->systemUnderTest = new GetEnvironmentVersions(
            $this->cacheMock,
            $productMetadataMock,
            $this->composerInfoMock,
            $this->fileIoMock,
            $this->serializerMock,
            $this->loggerMock
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

    public function testEverythingIsWorkingInstalledViaComposer(): void
    {
        $this->composerInfoMock->expects($this->once())->method('getInstalledMagentoPackages')->willReturn([
            'rvvup/module-magento-payments' => [
                'version' => '0.1.0'
            ],
        ]);
        $this->assertEquals(
            $this->getEnvironmentVersionsExecuteDefault(),
            $this->systemUnderTest->execute(),
            "Unexpected value when testing composer-based install UA string"
        );
    }

    public function testEverythingIsWorkingInstalledInAppCode(): void
    {
        $this->composerInfoMock->expects($this->once())->method('getInstalledMagentoPackages')->willReturn([]);
        $path = dirname((new ReflectionClass(GetEnvironmentVersions::class))->getFileName())
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'composer.json';
        $this->fileIoMock->expects($this->once())->method('fileExists')->with($path)->willReturn(true);
        $this->fileIoMock->expects($this->once())->method('read')->with($path)->willReturn('{"version": "0.0.1"}');
        $this->serializerMock->expects($this->once())->method('unserialize')->willReturn(['version' => '0.1.0']);

        $this->assertEquals(
            $this->getEnvironmentVersionsExecuteDefault(),
            $this->systemUnderTest->execute(),
            "Unexpected value when testing app/code-based install UA string"
        );
    }

    public function testComposerJsonMissingVersionInAppCode(): void
    {
        $this->composerInfoMock->expects($this->once())->method('getInstalledMagentoPackages')->willReturn([]);
        $path = dirname((new ReflectionClass(GetEnvironmentVersions::class))->getFileName())
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'composer.json';
        $this->fileIoMock->expects($this->once())->method('fileExists')->with($path)->willReturn(true);
        $this->fileIoMock->expects($this->once())->method('read')->with($path)->willReturn('{}');
        $this->serializerMock->expects($this->once())->method('unserialize')->willReturn([]);

        $this->assertEquals(
            array_merge(
                $this->getEnvironmentVersionsExecuteDefault(),
                ['rvvp_module_version' => GetEnvironmentVersions::UNKNOWN_VERSION]
            ),
            $this->systemUnderTest->execute(),
            "Unexpected value when testing missing composer file UA string"
        );
    }

    public function testCorruptComposerJsonInAppCode(): void
    {
        $this->composerInfoMock->expects($this->once())->method('getInstalledMagentoPackages')->willReturn([]);
        $path = dirname((new ReflectionClass(GetEnvironmentVersions::class))->getFileName())
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'composer.json';
        $this->fileIoMock->expects($this->once())->method('fileExists')->with($path)->willReturn(true);
        $this->fileIoMock->expects($this->once())->method('read')->with($path)->willReturn('some corrupt data');
        $this->serializerMock->expects($this->once())->method('unserialize')->willThrowException(new InvalidArgumentException());

        $this->assertEquals(
            array_merge(
                $this->getEnvironmentVersionsExecuteDefault(),
                ['rvvp_module_version' => GetEnvironmentVersions::UNKNOWN_VERSION]
            ),
            $this->systemUnderTest->execute(),
            "Unexpected value when testing corrupt composer file fallback"
        );
    }

    public function testEmptyComposerJsonInAppCode(): void
    {
        $this->composerInfoMock->expects($this->once())->method('getInstalledMagentoPackages')->willReturn([]);
        $path = dirname((new ReflectionClass(GetEnvironmentVersions::class))->getFileName())
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'composer.json';
        $this->fileIoMock->expects($this->once())->method('fileExists')->with($path)->willReturn(true);
        $this->fileIoMock->expects($this->once())->method('read')->with($path)->willReturn('');
        $this->assertEquals(
            array_merge(
                $this->getEnvironmentVersionsExecuteDefault(),
                ['rvvp_module_version' => GetEnvironmentVersions::UNKNOWN_VERSION]
            ),
            $this->systemUnderTest->execute(),
            "Unexpected value when testing corrupt composer file fallback"
        );
    }

    public function testSuccessfulFallbackIfUnableToLocateVersion(): void
    {
        $this->composerInfoMock->expects($this->once())->method('getInstalledMagentoPackages')->willReturn([]);
        $this->composerInfoMock->expects($this->once())->method('getInstalledMagentoPackages')->willReturn([]);
        $path = dirname((new ReflectionClass(GetEnvironmentVersions::class))->getFileName())
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'composer.json';
        $this->fileIoMock->expects($this->once())->method('fileExists')->with($path)->willReturn(false);
        $this->assertEquals(
            array_merge(
                $this->getEnvironmentVersionsExecuteDefault(),
                ['rvvp_module_version' => GetEnvironmentVersions::UNKNOWN_VERSION]
            ),
            $this->systemUnderTest->execute(),
            "Unexpected value when testing missing composer version key fallback"
        );
    }

    public function testGeneratedEnvironmentVersionIsRetrievedFromCache(): void
    {
        $jsonEncode = json_encode($this->getEnvironmentVersionsExecuteDefault());

        $this->cacheMock
            ->expects($this->exactly(2))
            ->method('load')
            ->with(GetEnvironmentVersions::RVVUP_ENVIRONMENT_VERSIONS)
            ->willReturnOnConsecutiveCalls(
                false,
                $jsonEncode
            );

        $this->composerInfoMock
            ->expects($this->once())
            ->method('getInstalledMagentoPackages')
            ->willReturn([
                'rvvup/module-magento-payments' => [
                    'version' => '0.1.0'
                ]
            ]);

        $this->serializerMock
            ->expects($this->once())
            ->method('serialize')
            ->willReturn($jsonEncode);

        $this->serializerMock
            ->expects($this->once())
            ->method('unserialize')
            ->willReturn($this->getEnvironmentVersionsExecuteDefault());

        $this->cacheMock->expects($this->once())->method('save')->willReturn(true);

        $this->assertEquals(
            $this->getEnvironmentVersionsExecuteDefault(),
            $this->systemUnderTest->execute(),
            'Cache saving/loading has unexpected behaviour'
        );

        $this->assertEquals(
            $this->getEnvironmentVersionsExecuteDefault(),
            $this->systemUnderTest->execute(),
            'Cache saving/loading has unexpected behaviour'
        );
    }

    /**
     * Get default values of `execute` method return for test purposes.
     *
     * @return array
     */
    private function getEnvironmentVersionsExecuteDefault(): array
    {
        return [
            'rvvp_module_version' => '0.1.0',
            'php_version' => $this->phpVersion,
            'magento_version' => [
                'name' => 'Magento',
                'edition' => 'Community',
                'version' => '2.4.4'
            ]
        ];
    }
}
