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
use Magento\Framework\View\Helper\SecureHtmlRenderer;

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
     * @param Context $context
     * @param ComponentRegistrarInterface $componentRegistrar
     * @param ReadFactory $readFactory
     * @param array $data
     * @param SecureHtmlRenderer|null $secureRenderer
     */
    public function __construct(
        Context $context,
        ComponentRegistrarInterface $componentRegistrar,
        ReadFactory $readFactory,
        SecureHtmlRenderer $secureRenderer,
        array $data = []
    ) {
        $this->componentRegistrar = $componentRegistrar;
        $this->readFactory = $readFactory;
        parent::__construct(
            $context,
            $data,
            $secureRenderer
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
    private function getRvvupPluginVersion(): string
    {
        $path = $this->componentRegistrar->getPath(
            ComponentRegistrar::MODULE,
            'Rvvup_Payments'
        );
        $directoryRead = $this->readFactory->create($path);
        $composerJsonData = $directoryRead->readFile('composer.json');
        $data = json_decode($composerJsonData, true);
        return $data['version'] ?? '';
    }
}
