<?php
declare(strict_types=1);

namespace Rvvup\Payments\Plugin\Email;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\BillingAddressManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Model\BillingAddressManagement;
use Magento\Quote\Model\ShippingAddressAssignment;

class BillingAddress
{
    /** @var ShippingAddressAssignment */
    private $shippingAddressAssignment;

    /** @var CartRepositoryInterface */
    private $quoteRepository;

    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param ShippingAddressAssignment $shippingAddressAssignment
     */
    public function __construct(
        CartRepositoryInterface   $quoteRepository,
        ShippingAddressAssignment $shippingAddressAssignment
    ) {
        $this->shippingAddressAssignment = $shippingAddressAssignment;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param BillingAddressManagement $subject
     * @param int|null $cartId
     * @param AddressInterface $address
     * @param bool $useForShipping
     * @return array
     * @throws NoSuchEntityException
     */
    public function beforeAssign(
        BillingAddressManagementInterface $subject,
        int $cartId,
        AddressInterface $address,
        bool $useForShipping = false
    ): array {
        $quote = $this->quoteRepository->getActive($cartId);
        $email = $quote->getBillingAddress()->getEmail();
        if ($email) {
            if (!$address->getEmail()) {
                $address->setEmail($email);
            }
        }

        return [$cartId, $address, $useForShipping];
    }
}
