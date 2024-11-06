<?php

namespace Rvvup\Payments\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validator\Exception;
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

    /**
     * @param CartRepositoryInterface $quoteRepository
     * @param Hash $hashService
     * @param QuoteValidator $quoteValidator
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        Hash                    $hashService,
        QuoteValidator          $quoteValidator
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->hashService = $hashService;
        $this->quoteValidator = $quoteValidator;
    }

    /**
     * Validates a quote
     * @param Quote $quote
     * @return void
     * @throws LocalizedException
     * @throws Exception
     */
    public function validate(Quote $quote): void
    {
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

    /**
     * Prepares the quote with the right information
     * @param Quote $quote
     * @return Quote
     * @throws LocalizedException
     */
    public function prepare(Quote $quote): Quote
    {
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
