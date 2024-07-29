<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\ShippingAddressManagementInterface;
use Rvvup\Payments\Api\ShippingInformationManagementInterface;

class ShippingInformationManagement implements ShippingInformationManagementInterface
{

    /* @var CartRepositoryInterface $quoteRepository */
    private $quoteRepository;

    /** @var ShippingAddressManagementInterface $shippingAddressManagement */
    private $shippingAddressManagement;

    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param ShippingAddressManagementInterface $shippingAddressManagement
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        ShippingAddressManagementInterface $shippingAddressManagement
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->shippingAddressManagement = $shippingAddressManagement;
    }

    /**
     * @inheritDoc
     */
    public function saveAddressInformation(int $cartId, ShippingInformationInterface $addressInformation): void
    {
        $quote = $this->quoteRepository->getActive($cartId);
        $address = $addressInformation->getShippingAddress();
        if (!$quote->getCustomerEmail()) {
            $quote->setCustomerEmail($address->getEmail());
        }

        if (!$quote->isVirtual()) {
            $this->shippingAddressManagement->assign($quote->getId(), $address);
        }
    }
}
