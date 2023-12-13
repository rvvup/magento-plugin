<?php

declare(strict_types=1);

namespace Rvvup\Payments\Service;

use Magento\Quote\Model\Quote;

class Hash
{
    public function saveQuoteHash(Quote $quote): void
    {
        $payment = $quote->getPayment();
        $payment->setAdditionalInformation('quote_hash', $this->getHashForData($quote->getData()));
    }

    public function getHashForData(array $data): string
    {
        $hashedValues = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $hashedValues[$key] = $value;
            }
        }

        $output = implode(', ', array_map(
            function ($v, $k) { return sprintf("%s='%s'", $k, $v); },
            $hashedValues,
            array_keys($hashedValues)
        ));

        return hash('sha256',$output);
    }

}
