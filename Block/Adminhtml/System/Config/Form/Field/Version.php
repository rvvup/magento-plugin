<?php

declare(strict_types=1);

namespace Rvvup\Payments\Block\Adminhtml\System\Config\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Serialize\Serializer\Json;

class Version extends Field
{
    /**
     * @var ComponentRegistrarInterface
     */
    private ComponentRegistrarInterface $componentRegistrar;

    /**
     * @var ReadFactory
     */
    private ReadFactory $readFactory;

    /**
     * @var Json
     */
    private Json $serializer;

    /**
     * @param Context $context
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param ReadFactory $readFactory
     * @param Json $serializer
     * @param array $data
     */
    public function __construct(
        Context $context,
        ComponentRegistrarInterface $componentRegistrar,
        ReadFactory $readFactory,
        Json $serializer,
        array $data = []
    ) {
        $this->componentRegistrar = $componentRegistrar;
        $this->readFactory = $readFactory;
        $this->serializer = $serializer;
        parent::__construct(
            $context,
            $data
        );
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $element->setDisabled('disabled');
        $element->setValue($this->getRvvupPluginVersion());

        return $element->getElementHtml();
    }

    /**
     * @return string
     * @throws FileSystemException
     * @throws ValidatorException
     */
    public function getRvvupPluginVersion(): string
    {
        $path = $this->getPath();
        $composerJsonData = $this->getComposerData($path);
        return $this->getModuleVersion($composerJsonData);
    }

    /**
     * @return string|null
     */
    protected function getPath(): ?string
    {
        return $this->componentRegistrar->getPath(
            ComponentRegistrar::MODULE,
            'Rvvup_Payments'
        );
    }

    /**
     * @param string $path
     * @return string
     * @throws FileSystemException
     * @throws ValidatorException
     */
    protected function getComposerData(string $path): string
    {
        $directoryRead = $this->readFactory->create($path);
        try {
            return $directoryRead->readFile('composer.json');
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * @param string $composerJsonData
     * @return string
     */
    protected function getModuleVersion(string $composerJsonData): string
    {
        if (empty($composerJsonData)) {
            return '';
        }

        $data = $this->getSerializer()->unserialize($composerJsonData);
        return $data['version'] ?? '';
    }

    /**
     * @return Json
     */
    protected function getSerializer(): Json
    {
        return $this->serializer;
    }
}
