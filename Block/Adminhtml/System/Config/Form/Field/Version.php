<?php

declare(strict_types=1);

namespace Rvvup\Payments\Block\Adminhtml\System\Config\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Rvvup\Payments\Model\Environment\GetEnvironmentVersions;

class Version extends Field
{
    /**
     * @var GetEnvironmentVersions
     */
    private $environmentVersions;

    /**
     * @param Context $context
     * @param GetEnvironmentVersions $environmentVersions
     * @param array $data
     */
    public function __construct(
        Context $context,
        GetEnvironmentVersions $environmentVersions,
        array $data = []
    ) {
        $this->environmentVersions = $environmentVersions;
        parent::__construct(
            $context,
            $data
        );
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $element->setDisabled('disabled');
        $element->setValue($this->environmentVersions->getRvvupModuleVersion());

        return $element->getElementHtml();
    }
}
