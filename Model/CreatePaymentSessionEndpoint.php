<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;


use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\QuoteRepository;
use Rvvup\Payments\Api\CreatePaymentSessionInterface;
use Rvvup\Payments\Api\Data\CreatePaymentSessionResponseInterface;
use Rvvup\Payments\Api\Data\CreatePaymentSessionResponseInterfaceFactory;
use Rvvup\Payments\Service\PaymentSessionService;

class CreatePaymentSessionEndpoint implements CreatePaymentSessionInterface
{

    /**
     * @var \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;
    /** @var QuoteRepository */
    private $quoteRepository;
    /** @var PaymentSessionService */
    private $paymentSessionService;
    /**
     * @var CreatePaymentSessionResponseInterfaceFactory
     */
    private $responseFactory;

    /**
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param QuoteRepository $quoteRepository
     * @param PaymentSessionService $paymentSessionService
     * @param CreatePaymentSessionResponseInterfaceFactory $responseFactory
     */
    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        QuoteRepository                 $quoteRepository,
        PaymentSessionService                        $paymentSessionService,
        CreatePaymentSessionResponseInterfaceFactory $responseFactory

    )
    {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->quoteRepository = $quoteRepository;
        $this->paymentSessionService = $paymentSessionService;
        $this->responseFactory = $responseFactory;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function execute(string $cartId, string $checkoutId): CreatePaymentSessionResponseInterface
    {
        $quote = $this->quoteRepository->get((string)$this->maskedQuoteIdToQuoteId->execute($cartId));

        $paymentSession = $this->paymentSessionService->create($quote, $checkoutId);

        /** @var CreatePaymentSessionResponseInterface $response */
        $response = $this->responseFactory->create();
        $response->setPaymentSessionId($paymentSession["id"]);
        $response->setRedirectUrl("");
        return $response;
    }
}
