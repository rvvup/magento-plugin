<?php

namespace Rvvup\Payments\Model;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Rvvup\Payments\Api\ShippingInformationManagementInterface;

class GuestShippingInformationManagement implements \Rvvup\Payments\Api\GuestShippingInformationManagementInterface
{

    /** @var ShippingInformationManagementInterface */
    private $shippingInformationManagement;

    /** @var QuoteIdMaskFactory */
    private $quoteIdMaskFactory;

    /**
     * @param ShippingInformationManagementInterface $shippingInformationManagement
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     */
    public function __construct(
        ShippingInformationManagementInterface $shippingInformationManagement,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->shippingInformationManagement = $shippingInformationManagement;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * @inheritDoc
     */
    public function saveAddressInformation(string $cartId, ShippingInformationInterface $addressInformation): void
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $quoteId = $quoteIdMask->getQuoteId();
        $this->shippingInformationManagement->saveAddressInformation($quoteId, $addressInformation);
    }
}
