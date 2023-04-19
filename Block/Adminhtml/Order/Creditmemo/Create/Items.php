<?php

namespace Rvvup\Payments\Block\Adminhtml\Order\Creditmemo\Create;

use Magento\Backend\Block\Widget\Button;
use Rvvup\Payments\Gateway\Method;

class Items extends \Magento\Sales\Block\Adminhtml\Order\Creditmemo\Create\Items
{

    /**
     * Prepare child blocks
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        $this->getParentBlock()->_prepareLayout();

        $onclick = "submitAndReloadArea($('creditmemo_item_container'),'" . $this->getUpdateUrl() . "')";
        $this->addChild(
            'update_button',
            Button::class,
            ['label' => __('Update Qty\'s'), 'class' => 'update-button', 'onclick' => $onclick]
        );

        if ($this->getCreditmemo()->canRefund()) {
            if ($this->getCreditmemo()->getInvoice() && $this->getCreditmemo()->getInvoice()->getTransactionId()
                || strpos($this->getOrder()->getPayment()->getMethod(), Method::PAYMENT_TITLE_PREFIX) === 0 &&
                $this->getOrder()->getPayment()->getLastTransId()) {
                $this->addChild(
                    'submit_button',
                    Button::class,
                    [
                        'label' => __('Refund'),
                        'class' => 'save submit-button refund primary',
                        'onclick' => 'disableElements(\'submit-button\');submitCreditMemo()'
                    ]
                );
            } else {
                $this->addChild(
                    'submit_offline',
                    Button::class,
                    [
                        'label' => __('Refund Offline'),
                        'class' => 'save submit-button primary',
                        'onclick' => 'disableElements(\'submit-button\');submitCreditMemoOffline()'
                    ]
                );
            }
        } else {
            $this->addChild(
                'submit_button',
                Button::class,
                [
                    'label' => __('Refund Offline'),
                    'class' => 'save submit-button primary',
                    'onclick' => 'disableElements(\'submit-button\');submitCreditMemoOffline()'
                ]
            );
        }

        return $this;
    }
}
