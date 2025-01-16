<?php declare(strict_types=1);

namespace Rvvup\Payments\Block\Adminhtml\System\Config\Form\Field;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class LogLink extends Field
{
    /**
     * Returns element html
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $url = $this->_urlBuilder->getUrl('rvvup/log');
        return <<<HTML
<a href="$url">Today's Log File</a>
HTML;
    }
}
