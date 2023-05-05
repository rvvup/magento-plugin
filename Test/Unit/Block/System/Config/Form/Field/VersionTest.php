<?php

declare(strict_types=1);

namespace Unit\Block\System\Config\Form\Field;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Component\ComponentRegistrarInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Rvvup\Payments\Block\Adminhtml\System\Config\Form\Field\Version;

class VersionTest extends TestCase
{
    /**
     * @var (ComponentRegistrarInterface&MockObject)|MockObject
     */
    private $componentRegistrar;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var MockObject
     */
    private $version;

    /**
    * @return void
     */
    protected function setUp(): void
    {
        $this->componentRegistrar = $this->getMockBuilder(ComponentRegistrarInterface::class)->getMock();
        $this->serializer = new Json();

        $this->version = $this->getMockBuilder(Version::class)->disableOriginalConstructor()
            ->onlyMethods(['getComposerData', 'getPath', 'getSerializer'])
            ->getMock();

        $this->version->method('getSerializer')->willReturn($this->serializer);
    }

    /**
    * @return void
    * @throws FileSystemException
    * @throws ValidatorException
    */
    public function testGetRvvupPluginVersionExists()
    {
        $this->version->method('getPath')
            ->willReturn('/var/www/html/vendor/rvvup/payments');

        $this->version->method('getComposerData')->willReturn('{"version":"1.0.0"}');
        $this->assertEquals('1.0.0', $this->version->getRvvupPluginVersion());
    }

    /**
    * @return void
    * @throws FileSystemException
    * @throws ValidatorException
     */
    public function testGetRvvupPluginVersionEmpty()
    {
        $this->version->method('getPath')->willReturn('');
        $this->version->method('getComposerData')->willReturn('{}');
        $this->assertEquals('', $this->version->getRvvupPluginVersion());
    }
}
