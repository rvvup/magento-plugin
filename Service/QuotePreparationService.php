<?php

namespace Rvvup\Payments\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteValidator;

class QuotePreparationService
{

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var Hash
     */
    private $hashService;

    /**
     * @var QuoteValidator
     */
    private $quoteValidator;

    public function __construct(
        CartRepositoryInterface $quoteRepository,
        Hash                    $hashService,
        QuoteValidator          $quoteValidator
    )
    {
        $this->quoteRepository = $quoteRepository;
        $this->hashService = $hashService;
        $this->quoteValidator = $quoteValidator;
    }

    /**
     * Prepares a quote with necessary data and ensure the data is valid
     * @param Quote $quote
     * @param bool $skipValidation
     * @return Quote
     * @throws LocalizedException
     */
    public function prepare(Quote $quote, $skipValidation = false): Quote
    {
        if (!$skipValidation) {
            $this->quoteValidator->validateBeforeSubmit($quote);

            $billingAddress = $quote->getBillingAddress();
            $shippingAddress = $quote->getShippingAddress();
            if ($billingAddress === null) {
                throw new LocalizedException(__("Billing address is required. Please check and try again."));
            }
            if (!$quote->getIsVirtual() && $quote->getShippingAddress() === null) {
                throw new LocalizedException(__("Shipping address is required. Please check and try again."));
            }
            if ($billingAddress->getPostcode() === null) {
                throw new LocalizedException(__("Billing postcode is required. Please check and try again."));
            }
            if ($shippingAddress->getPostcode() === null) {
                throw new LocalizedException(__("Shipping postcode is required. Please check and try again."));
            }
        }

        $customerEmail = $this->getCustomerEmail($quote);
        $quote->setCustomerEmail($customerEmail);
        $quote->reserveOrderId();
        $this->quoteRepository->save($quote);
        $this->hashService->saveQuoteHash($quote);

        return $quote;
    }

    /**
     * @param Quote $quote
     * @return string customer email
     */
    private function getCustomerEmail(Quote $quote): ?string
    {
        if ($quote->getCustomerEmail()) {
            return $quote->getCustomerEmail();
        }
        $email = $quote->getBillingAddress()->getEmail();
        if (!$email) {
            $email = $quote->getShippingAddress()->getEmail();
        }
        return $email;
    }
}