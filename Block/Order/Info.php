<?php

declare(strict_types=1);

namespace Rvvup\Payments\Block\Order;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context as TemplateContext;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address\Renderer as AddressRenderer;

class Info extends \Magento\Sales\Block\Order\Info
{
    /** @var Order */
    private $order;

    /** @var Session */
    private $session;

    /**
     * @param TemplateContext $context
     * @param Registry $registry
     * @param PaymentHelper $paymentHelper
     * @param AddressRenderer $addressRenderer
     * @param Session $session
     * @param Order $order
     * @param array $data
     */
    public function __construct(
        TemplateContext $context,
        Registry $registry,
        PaymentHelper $paymentHelper,
        AddressRenderer $addressRenderer,
        Session $session,
        Order $order,
        array $data = []
    ) {
        $this->session = $session;
        $this->order = $order;
        parent::__construct($context, $registry, $paymentHelper, $addressRenderer, $data);
    }

    /**
     * @inheritDoc
     * @return void
     * @throws LocalizedException
     */
    protected function _prepareLayout(): void
    {
        $orderId = $this->getOrder()->getRealOrderId() ?: $this->session->getLastRealOrderId();
        if ($orderId) {
            $this->pageConfig->getTitle()->set(__('Order # %1', $orderId));
        }

        $payment = $this->getOrder()->getPayment() ?: $this->order->loadByIncrementId($orderId)->getPayment();

        if ($payment) {
            $infoBlock = $this->paymentHelper->getInfoBlock($payment, $this->getLayout());
            $this->setChild('payment_info', $infoBlock);
        }
    }
}
