<?php

declare(strict_types=1);

namespace Rvvup\Payments\Service;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\Payment;

class Hash
{
    /** @var Payment */
    private $paymentResource;

    /**
     * @param Payment $paymentResource
     */
    public function __construct(
        Payment $paymentResource
    ) {
        $this->paymentResource = $paymentResource;
    }

    /** Save current quote state to hash */
    public function saveQuoteHash(Quote $quote): void
    {
        $payment = $quote->getPayment();
        $payment->setAdditionalInformation('quote_hash', $this->getHashForData($quote));
        $this->paymentResource->save($payment);
    }

    /** Create hash for current quote state */
    public function getHashForData(Quote $quote): string
    {
        $hashedValues = [];
        foreach ($quote->getTotals() as $total) {
            $hashedValues[$total->getCode()] = $total->getValue();
        }
        foreach ($quote->getItems() as $item) {
            $hashedValues[$item->getSku()] = $item->getQty() . '_' . $item->getPrice();
        }

        $output = implode(
            ', ', array_map(
                function ($v, $k) {
                    return sprintf("%s='%s'", $k, $v);
                },
                $hashedValues,
                array_keys($hashedValues)
            )
        );

        return hash('sha256', $output);
    }
}