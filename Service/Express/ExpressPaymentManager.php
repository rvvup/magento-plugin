<?php

declare(strict_types=1);

namespace Rvvup\Payments\Service\Express;

use Magento\Checkout\Model\Type\Onepage;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\InputException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Rvvup\Payments\Service\Shipping\ShippingMethodService;

class ExpressPaymentManager
{

    /** @var CartRepositoryInterface */
    private $quoteRepository;

    /** @var Session */
    private $customerSession;

    /** @var ShippingMethodService */
    private $shippingMethodService;

    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param Session $customerSession
     * @param ShippingMethodService $shippingMethodService
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        Session $customerSession,
        ShippingMethodService $shippingMethodService
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->customerSession = $customerSession;
        $this->shippingMethodService = $shippingMethodService;
    }

    /**
     * @param Quote $quote
     * @param array $address
     * @return array $result
     * @throws InputException
     */
    public function updateShippingAddress(Quote $quote, array $address): array
    {
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress
            ->setCountryId($address['countryCode'])
            ->setCity($address['city'] ?? null)
            ->setRegion($address['state'] ?? null)
//            ->setRegionId() Set it by looking up state and getting the id
            ->setPostcode($address['postcode'] ?? null)
            ->setCollectShippingRates(true);

        $shippingMethods = $this->shippingMethodService->setFirstShippingMethodInQuote($quote, $shippingAddress)
        ["availableShippingMethods"];

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();

        $this->quoteRepository->save($quote);
        return ['quote' => $quote, 'shippingMethods' => $shippingMethods];
    }

    /**
     * @param Quote $quote
     * @param array $data
     * @return Quote
     */
    public function updateQuoteBeforePaymentAuth(Quote $quote, array $data): Quote
    {
        if (!$quote->isVirtual() &&
            isset($data['shipping']['address']) &&
            isset($data['shipping']['address']['postcode']) && // Only missing if shipping was not used in express sheet
            isset($data['shipping']['contact'])
        ) {
            $this->setUpdatedAddressDetails(
                $quote->getShippingAddress(),
                $data['shipping']['contact'],
                $data['shipping']['address']
            );
        }

        if (isset($data['billing']['address']) && isset($data['billing']['contact'])) {
            $contact = $data['billing']['contact'];
            $this->setUpdatedAddressDetails($quote->getbillingAddress(), $contact, $data['billing']['address']);

            $quote->setCustomerFirstname($contact['givenName'] ?? null)
                ->setCustomerLastname($contact['surname'] ?? null);

            if ($this->customerSession->isLoggedIn()) {
                $quote->setCheckoutMethod(Onepage::METHOD_CUSTOMER)
                    ->setCustomerId($this->customerSession->getCustomerId());
            } else {
                $quote->setCheckoutMethod(Onepage::METHOD_GUEST)
                    ->setCustomerId(0)
                    ->setCustomerGroupId(GroupInterface::NOT_LOGGED_IN_ID)
                    ->setCustomerEmail($quote->getBillingAddress()->getEmail())
                    ->setCustomerIsGuest(true);
            }
        }

        if (isset($data['paymentMethod'])) {
            $quote->getPayment()->setMethod('rvvup_' . $data['paymentMethod']);
        }

        $shippingAddress = $quote->getShippingAddress();
        $selectedMethod = $shippingAddress->getShippingMethod();
        // If the shipping method is not set then the first method was displayed in the sheet and was not changed
        if (empty($selectedMethod)) {
            $this->shippingMethodService->setFirstShippingMethodInQuote($quote, $shippingAddress);
        }

        $quote->setTotalsCollectedFlag(false);
        $quote->collectTotals();

        $this->quoteRepository->save($quote);
        return $quote;
    }

    /**
     * @param Address $quoteAddress
     * @param array $contact
     * @param array $address
     * @return void
     */
    public function setUpdatedAddressDetails(Quote\Address $quoteAddress, array $contact, array $address): void
    {
        $quoteAddress
            ->setFirstname($contact['givenName'] ?? null)
            ->setLastname($contact['surname'] ?? null)
            ->setEmail($contact['email'] ?? null)
            ->setTelephone($contact['phoneNumber'] ?? null)
            ->setStreet($address['addressLines'] ?? null)
            ->setCountryId($address['countryCode'] ?? null)
            ->setRegion($address['state'] ?? null)
//            ->setRegionId() Set it by looking up state and getting the id
            ->setPostcode($address['postcode'] ?? null)
            ->setCity($address['city'] ?? null);
    }
}
