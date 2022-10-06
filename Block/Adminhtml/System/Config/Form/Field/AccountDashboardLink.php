<?php

declare(strict_types=1);

namespace Rvvup\Payments\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class AccountDashboardLink extends Field
{
    /**
     * Path to template file in theme.
     *
     * @var string
     */
    protected $_template = 'Rvvup_Payments::system/config/form/field/account-dashboard-link.phtml';

    /**
     * Retrieve HTML markup for given form element
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        // Remove scope label
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    /**
     * Retrieve element HTML markup
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->addData([
            'id' => 'account_dashboard_link_id',
            'link_title' => __('Rvvup Dashboard'),
            'onclick' => 'javascript:window.open("https://dashboard.rvvup.com", "_blank").focus(); return false;'
        ]);

        return $this->_toHtml();
    }
}
