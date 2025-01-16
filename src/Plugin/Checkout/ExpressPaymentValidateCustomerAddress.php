<?php

declare(strict_types=1);

namespace Rvvup\Payments\Plugin\Checkout;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Framework\Validator\Exception;
use Rvvup\Payments\Model\Checks\HasCartExpressPaymentInterface;
use Rvvup\Payments\Model\Validation\IsValidAddress;

class ExpressPaymentValidateCustomerAddress
{
    /**
     * @var \Rvvup\Payments\Model\Checks\HasCartExpressPaymentInterface
     */
    private $hasCartExpressPayment;

    /**
     * @var \Rvvup\Payments\Model\Validation\IsValidAddress
     */
    private $isValidAddress;

    /**
     * @param \Rvvup\Payments\Model\Checks\HasCartExpressPaymentInterface $hasCartExpressPayment
     * @param \Rvvup\Payments\Model\Validation\IsValidAddress $isValidAddress
     * @return void
     */
    public function __construct(HasCartExpressPaymentInterface $hasCartExpressPayment, IsValidAddress $isValidAddress)
    {
        $this->hasCartExpressPayment = $hasCartExpressPayment;
        $this->isValidAddress = $isValidAddress;
    }

    /**
     * Validate the shipping address on the method for Rvvup express payments.
     *
     * @param \Magento\Checkout\Api\ShippingInformationManagementInterface $subject
     * @param int $cartId
     * @param \Magento\Checkout\Api\Data\ShippingInformationInterface $addressInformation
     * @return null
     * @throws \Magento\Framework\Validator\Exception
     */
    public function beforeSaveAddressInformation(
        ShippingInformationManagementInterface $subject,
        $cartId,
        ShippingInformationInterface $addressInformation
    ) {
        /** @var \Magento\Quote\Api\Data\AddressInterface|\Magento\Customer\Model\Address\AbstractAddress $shippingAddress */
        $shippingAddress = $addressInformation->getShippingAddress();

        // Init validation shipping address is not null
        if ($shippingAddress === null) {
            return null;
        }

        if (!$this->hasCartExpressPayment->execute((int) $cartId)) {
            return null;
        }

        // Now validate the address.
        $validationResult = $this->isValidAddress->execute($shippingAddress);

        // No action if valid.
        if ($validationResult->isValid()) {
            return null;
        }

        $messages = $validationResult->getErrors();
        $defaultMessage = array_shift($messages);

        if ($defaultMessage && !empty($messages)) {
            $defaultMessage .= ' %1';
        }

        if ($defaultMessage) {
            throw new Exception(__($defaultMessage, implode(' ', $messages)));
        }

        return null;
    }
}
