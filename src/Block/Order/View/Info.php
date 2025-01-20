<?php
declare(strict_types=1);

namespace Rvvup\Payments\Block\Order\View;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Sales\Block\Adminhtml\Order\AbstractOrder;
use Magento\Sales\Helper\Admin;
use Magento\Store\Model\ScopeInterface;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\SdkProxy;
use Rvvup\Payments\Service\PaymentLink;

class Info extends AbstractOrder
{
    /** @var ConfigInterface */
    private $config;

    /** @var SdkProxy */
    private $sdkProxy;

    /** @var PaymentLink */
    private $paymentLinkService;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param Admin $adminHelper
     * @param ConfigInterface $config
     * @param SdkProxy $sdkProxy
     * @param PaymentLink $paymentLinkService
     */
    public function __construct(
        Context $context,
        Registry $registry,
        Admin $adminHelper,
        ConfigInterface $config,
        SdkProxy $sdkProxy,
        PaymentLink $paymentLinkService
    ) {
        $this->config = $config;
        $this->sdkProxy = $sdkProxy;
        $this->paymentLinkService = $paymentLinkService;
        parent::__construct(
            $context,
            $registry,
            $adminHelper
        );
    }

    /**
     * @return bool
     * @throws LocalizedException
     */
    public function shouldDisplayRvvup(): bool
    {
        if ($this->getOrder()) {
            $order = $this->getOrder();
            if ($this->config->isActive(ScopeInterface::SCOPE_STORE, $order->getStoreId())) {
                $payment = $order->getPayment();
                if ($payment) {
                    if (in_array($payment->getMethod(), ['rvvup_virtual-terminal','rvvup_payment-link'])) {
                        if (count($order->getInvoiceCollection()->getItems()) == 0) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * @return bool
     * @throws LocalizedException
     */
    public function isMotoAvailable(): bool
    {
        $amount = $this->getOrder()->getGrandTotal();
        $currency = $this->getOrder()->getOrderCurrencyCode();
        if ($this->getOrder()->getPayment()->getMethod() == 'rvvup_virtual-terminal') {
            $methods = $this->sdkProxy->getMethods($amount, $currency);
            foreach ($methods as $method) {
                if (isset($method['settings']['motoEnabled']) && $method['settings']['motoEnabled']) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @return bool
     * @throws LocalizedException
     */
    public function isPaymentLinkOrder(): bool
    {
        if ($this->getOrder()->getPayment()->getMethod() == 'rvvup_payment-link') {
            return true;
        }
        return false;
    }

    /**
     * @return string|null
     * @throws LocalizedException
     */
    public function getPaymentLink(): ?string
    {
        if ($this->shouldDisplayRvvup()) {
            $payment = $this->paymentLinkService->getQuotePaymentByOrder($this->getOrder());
            $message = $payment->getAdditionalInformation(Method::PAYMENT_LINK_MESSAGE);
            if ($message && is_string($message)) {
                return $message;
            }
        }

        return null;
    }
}
