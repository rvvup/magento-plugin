<?php

declare(strict_types=1);

namespace Rvvup\Payments\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Rvvup\Payments\Model\OrderDataBuilder;

class InitializeDataBuilder implements BuilderInterface
{
    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var \Rvvup\Payments\Model\OrderDataBuilder
     */
    private $orderDataBuilder;

    /**
     * @param \Magento\Quote\Api\CartRepositoryInterface $cartRepository
     * @param \Rvvup\Payments\Model\OrderDataBuilder $orderDataBuilder
     * @return void
     */
    public function __construct(CartRepositoryInterface $cartRepository, OrderDataBuilder $orderDataBuilder)
    {
        $this->cartRepository = $cartRepository;
        $this->orderDataBuilder = $orderDataBuilder;
    }

    /**
     * @param array $buildSubject
     * @return array
     * @throws \Rvvup\Payments\Exception\QuoteValidationException|\Magento\Framework\Exception\NoSuchEntityException
     */
    public function build(array $buildSubject): array
    {
        $paymentDataObject = SubjectReader::readPayment($buildSubject);

        // Get the quote.
        $cart = $this->cartRepository->get($paymentDataObject->getPayment()->getOrder()->getQuoteId());

        // Build the Rvvup request data.
        return $this->orderDataBuilder->build($cart);
    }
}
