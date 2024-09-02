<?php

declare(strict_types=1);

namespace Rvvup\Payments\Api;

use Magento\Checkout\Api\Data\PaymentDetailsInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterface;

interface ShippingInformationManagementInterface
{
    /**
     * @param int $cartId
     * @param ShippingInformationInterface $addressInformation
     * @return void
     */
    public function saveAddressInformation(
        int                          $cartId,
        ShippingInformationInterface $addressInformation
    ): void;
}
