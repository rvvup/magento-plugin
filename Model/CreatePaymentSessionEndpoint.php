<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Checkout\Model\GuestPaymentInformationManagement;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validator\Exception;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\QuoteRepository;
use Rvvup\Payments\Api\CreatePaymentSessionInterface;
use Rvvup\Payments\Api\Data\CreatePaymentSessionResponseInterface;
use Rvvup\Payments\Api\Data\CreatePaymentSessionResponseInterfaceFactory;
use Rvvup\Payments\Service\PaymentSessionService;

class CreatePaymentSessionEndpoint implements CreatePaymentSessionInterface
{

    /** @var \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface */
    private $maskedQuoteIdToQuoteId;
    /** @var QuoteRepository */
    private $quoteRepository;
    /** @var PaymentSessionService */
    private $paymentSessionService;
    /**  @var CreatePaymentSessionResponseInterfaceFactory */
    private $responseFactory;
    /*** @var GuestPaymentInformationManagement */
    private $guestPaymentInformationManagement;
    /**
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param QuoteRepository $quoteRepository
     * @param PaymentSessionService $paymentSessionService
     * @param CreatePaymentSessionResponseInterfaceFactory $responseFactory
     * @param GuestPaymentInformationManagement $guestPaymentInformationManagement
     */
    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        QuoteRepository                 $quoteRepository,
        PaymentSessionService                        $paymentSessionService,
        CreatePaymentSessionResponseInterfaceFactory $responseFactory,
        GuestPaymentInformationManagement $guestPaymentInformationManagement

    )
    {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->quoteRepository = $quoteRepository;
        $this->paymentSessionService = $paymentSessionService;
        $this->responseFactory = $responseFactory;
        $this->guestPaymentInformationManagement = $guestPaymentInformationManagement;
    }

    /**
     * @inheritdoc
     * @param string $cartId
     * @param string $checkoutId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface $billingAddress
     * @return CreatePaymentSessionResponseInterface
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws Exception
     */
    public function execute(
        string           $cartId,
        string           $checkoutId,
        string           $email,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress
    ): CreatePaymentSessionResponseInterface {
        $this->guestPaymentInformationManagement->savePaymentInformation(
            $cartId,
            $email,
            $paymentMethod,
            $billingAddress
        );

        $quote = $this->quoteRepository->get((string)$this->maskedQuoteIdToQuoteId->execute($cartId));
        $paymentSession =
            $this->paymentSessionService->create($quote, $checkoutId);

        /** @var CreatePaymentSessionResponseInterface $response */
        $response = $this->responseFactory->create();
        $response->setPaymentSessionId($paymentSession["id"]);
        $response->setRedirectUrl("");
        return $response;
    }
}
