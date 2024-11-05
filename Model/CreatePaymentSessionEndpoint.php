<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;


use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\QuoteRepository;
use Rvvup\Payments\Api\CreatePaymentSessionInterface;
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
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param QuoteRepository $quoteRepository
     * @param PaymentSessionService $paymentSessionService
     */
    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        QuoteRepository                 $quoteRepository,
        PaymentSessionService           $paymentSessionService

    )
    {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->quoteRepository = $quoteRepository;
        $this->paymentSessionService = $paymentSessionService;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function execute(string $cartId, string $checkoutId): string
    {
        $quote = $this->quoteRepository->get((string)$this->maskedQuoteIdToQuoteId->execute($cartId));

        $paymentSession = $this->paymentSessionService->create($quote, $checkoutId);

        return $paymentSession["id"];
    }
}
