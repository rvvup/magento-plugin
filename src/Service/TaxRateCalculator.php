<?php

namespace Rvvup\Payments\Service;

use Exception;
use Magento\Catalog\Model\Product;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Tax\Model\Calculation;

class TaxRateCalculator
{

    /** @var array<int, string> */
    private $taxRateCache = [];

    /** @var Calculation */
    private $taxCalculation;

    /**
     * @param Calculation $taxCalculation
     */
    public function __construct(
        Calculation $taxCalculation
    ) {
        $this->taxCalculation = $taxCalculation;
    }

    /**
     * @param CartInterface $quote
     * @param Product $product
     * @return string|null
     */
    public function getItemTaxRate(CartInterface $quote, Product $product): ?string
    {
        try {
            $request = $this->taxCalculation->getRateRequest(
                $quote->getShippingAddress(),
                $quote->getBillingAddress(),
                $quote->getCustomerTaxClassId(),
                $quote->getStore(),
            );

            $taxClassId = $product->getTaxClassId();
            if ($taxClassId === null) {
                return null;
            }
            $taxClassId = (int) $taxClassId;

            if (isset($this->taxRateCache[$taxClassId])) {
                return $this->taxRateCache[$taxClassId];
            }

            $request->setData('product_class_id', $taxClassId);
            $rate = $this->taxCalculation->getRate($request);

            if ($rate == 0) {
                $this->taxRateCache[$taxClassId] = null;
                return null;
            }

            $rateString = (string) $rate;
            $this->taxRateCache[$taxClassId] = $rateString;
            return $rateString;

        } catch (Exception $e) {
            return null;
        }
    }
}
