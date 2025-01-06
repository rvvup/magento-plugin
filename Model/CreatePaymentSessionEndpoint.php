<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validator\Exception;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\QuoteRepository;
use Rvvup\ApiException;
use Rvvup\Payments\Api\CreatePaymentSessionInterface;
use Rvvup\Payments\Api\Data\PaymentSessionInterface;
use Rvvup\Payments\Service\PaymentSessionService;

class CreatePaymentSessionEndpoint implements CreatePaymentSessionInterface
{

    /** @var MaskedQuoteIdToQuoteIdInterface */
    private $maskedQuoteIdToQuoteId;
    /** @var QuoteRepository */
    private $quoteRepository;
    /** @var PaymentSessionService */
    private $paymentSessionService;
    /*** @var GuestPaymentInformationManagementInterface */
    private $guestPaymentInformationManagement;
    /*** @var PaymentInformationManagementInterface */
    private $paymentInformationManagement;

    /**
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param QuoteRepository $quoteRepository
     * @param PaymentSessionService $paymentSessionService
     * @param GuestPaymentInformationManagementInterface $guestPaymentInformationManagement
     * @param PaymentInformationManagementInterface $paymentInformationManagement
     */
    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        QuoteRepository                 $quoteRepository,
        PaymentSessionService                        $paymentSessionService,
        GuestPaymentInformationManagementInterface $guestPaymentInformationManagement,
        PaymentInformationManagementInterface      $paymentInformationManagement
    ) {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->quoteRepository = $quoteRepository;
        $this->paymentSessionService = $paymentSessionService;
        $this->guestPaymentInformationManagement = $guestPaymentInformationManagement;
        $this->paymentInformationManagement = $paymentInformationManagement;
    }

    /**
     * @param string $cartId
     * @param string $checkoutId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface $billingAddress
     * @return PaymentSessionInterface
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws Exception|ApiException
     */
    public function guestRoute(
        string           $cartId,
        string           $checkoutId,
        string           $email,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress
    ): PaymentSessionInterface {
        $this->guestPaymentInformationManagement->savePaymentInformation(
            $cartId,
            $email,
            $paymentMethod,
            $billingAddress
        );

        $quote = $this->quoteRepository->get((string) $this->maskedQuoteIdToQuoteId->execute($cartId));
        return $this->paymentSessionService->create($quote, $checkoutId);
    }

    /**
     * @param string $customerId
     * @param string $cartId
     * @param string $checkoutId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface $billingAddress
     * @return PaymentSessionInterface
     * @throws AlreadyExistsException
     * @throws ApiException
     * @throws Exception
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     */
    public function customerRoute(
        string           $customerId,
        string           $cartId,
        string           $checkoutId,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress
    ): PaymentSessionInterface {
        $this->paymentInformationManagement->savePaymentInformation(
            $cartId,
            $paymentMethod,
            $billingAddress,
        );

        $quote = $this->quoteRepository->get($cartId);
        return $this->paymentSessionService->create($quote, $checkoutId);
    }
}
