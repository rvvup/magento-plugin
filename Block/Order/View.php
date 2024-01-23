<?php

declare(strict_types=1);

namespace Rvvup\Payments\Block\Order;

use Klarna\Core\Api\OrderRepositoryInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Http\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data;
use Magento\Sales\Model\Order;

class View extends \Magento\Sales\Block\Order\View
{
    /** @var Session */
    private $session;

    /** @var Order */
    private $order;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param Registry $registry
     * @param Context $httpContext
     * @param Data $paymentHelper
     * @param Session $session
     * @param OrderRepositoryInterface $orderRepository
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        Registry $registry,
        Context $httpContext,
        Data $paymentHelper,
        Session $session,
        Order $order,
        array $data = []
    ) {
        $this->session = $session;
        $this->order = $order;
        parent::__construct($context, $registry, $httpContext, $paymentHelper, $data);
    }

    /**
     * @inheritDoc
     * @return void
     * @throws LocalizedException
     */
    protected function _prepareLayout(): void
    {
        $orderId = $this->getOrder()->getRealOrderId();
        $payment = $this->getOrder()->getPayment();
        if (!$orderId) {
            $orderId = $this->session->getLastRealOrderId();
        }
        if (!$payment) {
            $order = $this->order->loadByIncrementId($orderId);
            $payment = $order->getPayment();
        }
        $this->pageConfig->getTitle()->set(__('Order # %1', $orderId));
        $infoBlock = $this->_paymentHelper->getInfoBlock($payment, $this->getLayout());
        $this->setChild('payment_info', $infoBlock);
    }
}
