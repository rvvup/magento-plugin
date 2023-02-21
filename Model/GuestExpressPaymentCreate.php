<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Rvvup\Payments\Api\GuestExpressPaymentCreateInterface;
use Rvvup\Payments\Api\ExpressPaymentCreateInterface;

class GuestExpressPaymentCreate implements GuestExpressPaymentCreateInterface
{
    /**
     * @var \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var \Rvvup\Payments\Api\ExpressPaymentCreateInterface
     */
    private $expressPaymentCreate;

    /**
     * @param \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param \Rvvup\Payments\Api\ExpressPaymentCreateInterface $expressPaymentCreate
     * @return void
     */
    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        ExpressPaymentCreateInterface $expressPaymentCreate
    ) {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->expressPaymentCreate = $expressPaymentCreate;
    }

    /**
     * @param string $cartId
     * @param string $methodCode
     * @return \Rvvup\Payments\Api\Data\PaymentActionInterface[]
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Rvvup\Payments\Exception\PaymentValidationException
     */
    public function execute(string $cartId, string $methodCode): array
    {
        return $this->expressPaymentCreate->execute(
            (string) $this->maskedQuoteIdToQuoteId->execute($cartId),
            $methodCode
        );
    }
}
