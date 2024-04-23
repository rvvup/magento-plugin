<?php
declare(strict_types=1);

namespace Rvvup\Payments\Block\Order\View;

use Magento\Backend\Block\Template\Context;
use Magento\Customer\Api\CustomerMetadataInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\Metadata\ElementFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Sales\Block\Adminhtml\Order\View\Info as MagentoInfo;
use Magento\Sales\Helper\Admin;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address\Renderer;
use Magento\Store\Model\ScopeInterface;
use Rvvup\Payments\Model\ConfigInterface;
use Rvvup\Payments\Model\RvvupConfigProvider;

class Info extends MagentoInfo
{
    /** @var ConfigInterface */
    private $config;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param Admin $adminHelper
     * @param GroupRepositoryInterface $groupRepository
     * @param CustomerMetadataInterface $metadata
     * @param ElementFactory $elementFactory
     * @param Renderer $addressRenderer
     * @param ConfigInterface $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        Admin $adminHelper,
        GroupRepositoryInterface $groupRepository,
        CustomerMetadataInterface $metadata,
        ElementFactory $elementFactory,
        Renderer $addressRenderer,
        ConfigInterface $config,
        array $data = []
    ) {
        $this->config = $config;
        parent::__construct(
            $context,
            $registry,
            $adminHelper,
            $groupRepository,
            $metadata,
            $elementFactory,
            $addressRenderer,
            $data
        );
    }

    /**
     * @return bool
     * @throws LocalizedException
     */
    public function shouldDisplayMoto(): bool
    {
        if ($this->getOrder()) {
            $order = $this->getOrder();
            if ($this->config->isActive(ScopeInterface::SCOPE_STORE, $order->getStoreId())) {
                $payment = $order->getPayment();
                if ($payment && $payment->getMethod() == RvvupConfigProvider::CODE) {
                    if ($this->getOrder()->getState() == Order::STATE_NEW) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * @return string|null
     * @throws LocalizedException
     */
    public function getPaymentLink(): ?string
    {
        if ($this->shouldDisplayMoto()) {
            $payment = $this->getOrder()->getPayment();
            $message = $payment->getAdditionalInformation('rvvup_payment_link_message');
            if ($message && is_string($message)) {
                return $message;
            }
        }

        return null;
    }
}
