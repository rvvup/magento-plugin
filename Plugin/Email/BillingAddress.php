<?php
declare(strict_types=1);

namespace Rvvup\Payments\Plugin\Email;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Model\BillingAddressManagement;
use Magento\Quote\Model\ShippingAddressAssignment;
use Psr\Log\LoggerInterface as Logger;

class BillingAddress
{
    /** @var ShippingAddressAssignment */
    private $shippingAddressAssignment;

    /** @var CartRepositoryInterface */
    private $quoteRepository;

    /** @var Logger */
    private $logger;

    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param ShippingAddressAssignment $shippingAddressAssignment
     * @param Logger $logger
     */
    public function __construct(
        CartRepositoryInterface   $quoteRepository,
        ShippingAddressAssignment $shippingAddressAssignment,
        Logger                    $logger
    ) {
        $this->shippingAddressAssignment = $shippingAddressAssignment;
        $this->logger = $logger;
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
        BillingAddressManagement $subject,
        ?int $cartId,
        AddressInterface $address,
        bool $useForShipping  =  false
    ): array {
        if ($cartId) {
            $quote = $this->quoteRepository->getActive($cartId);
            $email = $quote->getBillingAddress()->getEmail();
            if ($email && !$quote->getCustomerEmail()) {
                $quote->setCustomerEmail($email);
                $this->quoteRepository->save($quote);
            }
        }

        return [$cartId, $address, $useForShipping];
    }
}
