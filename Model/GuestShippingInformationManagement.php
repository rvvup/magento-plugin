<?php

namespace Rvvup\Payments\Model;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Rvvup\Payments\Api\GuestShippingInformationManagementInterface;
use Rvvup\Payments\Api\ShippingInformationManagementInterface;

class GuestShippingInformationManagement implements GuestShippingInformationManagementInterface
{

    /** @var ShippingInformationManagementInterface */
    private $shippingInformationManagement;

    /** @var QuoteIdMaskResource */
    private $quoteIdMaskResource;

    /** @var QuoteIdMaskFactory */
    private $quoteIdMaskFactory;

    /**
     * @param ShippingInformationManagementInterface $shippingInformationManagement
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteIdMaskResource $quoteIdMaskResource
     */
    public function __construct(
        ShippingInformationManagementInterface $shippingInformationManagement,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        QuoteIdMaskResource $quoteIdMaskResource
    ) {
        $this->shippingInformationManagement = $shippingInformationManagement;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteIdMaskResource = $quoteIdMaskResource;
    }

    /**
     * @inheritDoc
     */
    public function saveAddressInformation(string $cartId, ShippingInformationInterface $addressInformation): void
    {
        $object = $this->quoteIdMaskFactory->create();
        $this->quoteIdMaskResource->load($object, $cartId, 'masked_id');
        $quoteId = $object->getQuoteId();
        $this->shippingInformationManagement->saveAddressInformation($quoteId, $addressInformation);
    }
}
