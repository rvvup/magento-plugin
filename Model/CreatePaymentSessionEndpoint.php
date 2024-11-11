<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlFactory;
use Magento\Framework\UrlInterface;
use Magento\Framework\Validator\Exception;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\QuoteRepository;
use Rvvup\ApiException;
use Rvvup\Payments\Api\CreatePaymentSessionInterface;
use Rvvup\Payments\Api\Data\CreatePaymentSessionResponseInterface;
use Rvvup\Payments\Api\Data\CreatePaymentSessionResponseInterfaceFactory;
use Rvvup\Payments\Controller\Redirect\In;
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
    /*** @var GuestPaymentInformationManagementInterface */
    private $guestPaymentInformationManagement;
    /*** @var PaymentInformationManagementInterface */
    private $paymentInformationManagement;
    /** @var UrlFactory */
    protected $urlFactory;

    /**
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param QuoteRepository $quoteRepository
     * @param PaymentSessionService $paymentSessionService
     * @param CreatePaymentSessionResponseInterfaceFactory $responseFactory
     * @param GuestPaymentInformationManagementInterface $guestPaymentInformationManagement
     * @param PaymentInformationManagementInterface $paymentInformationManagement
     * @param UrlFactory $urlFactory
     */
    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        QuoteRepository                 $quoteRepository,
        PaymentSessionService                        $paymentSessionService,
        CreatePaymentSessionResponseInterfaceFactory $responseFactory,
        GuestPaymentInformationManagementInterface $guestPaymentInformationManagement,
        PaymentInformationManagementInterface      $paymentInformationManagement,
        UrlFactory $urlFactory
    ) {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->quoteRepository = $quoteRepository;
        $this->paymentSessionService = $paymentSessionService;
        $this->responseFactory = $responseFactory;
        $this->guestPaymentInformationManagement = $guestPaymentInformationManagement;
        $this->paymentInformationManagement = $paymentInformationManagement;
        $this->urlFactory = $urlFactory;
    }

    /**
     * @param string $cartId
     * @param string $checkoutId
     * @param string $email
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface $billingAddress
     * @return CreatePaymentSessionResponseInterface
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
    ): CreatePaymentSessionResponseInterface {
        $this->guestPaymentInformationManagement->savePaymentInformation(
            $cartId,
            $email,
            $paymentMethod,
            $billingAddress
        );

        return $this->createPaymentSession((string) $this->maskedQuoteIdToQuoteId->execute($cartId), $checkoutId);
    }

    /**
     * @param string $customerId
     * @param string $cartId
     * @param string $checkoutId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface $billingAddress
     * @return CreatePaymentSessionResponseInterface
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
    ): CreatePaymentSessionResponseInterface {
        $this->paymentInformationManagement->savePaymentInformation(
            $cartId,
            $paymentMethod,
            $billingAddress,
        );

        return $this->createPaymentSession($cartId, $checkoutId);
    }

    /**
     * @param string $cartId
     * @param string $checkoutId
     * @return CreatePaymentSessionResponseInterface
     * @throws AlreadyExistsException
     * @throws ApiException
     * @throws Exception
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function createPaymentSession(string $cartId, string $checkoutId): CreatePaymentSessionResponseInterface
    {
        $quote = $this->quoteRepository->get($cartId);

        $paymentSession = $this->paymentSessionService->create($quote, $checkoutId);

        /** @var CreatePaymentSessionResponseInterface $response */
        $response = $this->responseFactory->create();
        $response->setPaymentSessionId($paymentSession["id"]);
        $url = $this->urlFactory->create();
        $url->setQueryParam(In::PARAM_RVVUP_ORDER_ID, $paymentSession["id"]);
        $response->setRedirectUrl($url->getUrl('rvvup/redirect/in'));
        return $response;
    }
}
