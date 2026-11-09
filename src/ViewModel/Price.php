<?php

declare(strict_types=1);

namespace Rvvup\Payments\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Data;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Tax\Model\Config as TaxConfig;

class Price implements ArgumentInterface
{

    /**
     * @var Data
     */
    private $taxHelper;

    /**
     * @var TaxConfig
     */
    private $taxConfig;

    public function __construct(
        Data $taxHelper,
        TaxConfig $taxConfig
    ) {
        $this->taxHelper = $taxHelper;
        $this->taxConfig = $taxConfig;
    }

    public function getPrice(ProductInterface $product): float
    {
        if ($this->taxConfig->getPriceDisplayType() == TaxConfig::DISPLAY_TYPE_BOTH) {
            return $this->taxHelper->getTaxPrice($product, (float)$product->getFinalPrice(), true);
        }

        return $this->taxHelper->getTaxPrice($product, (float)$product->getFinalPrice());
    }

    /**
     * Price the customer pays including tax, regardless of the store's tax display setting.
     * Dynamic bundles with nothing selected report 0, so fall back to their minimal price.
     *
     * @param ProductInterface&\Magento\Catalog\Model\Product $product
     */
    public function getPriceIncludingTax(ProductInterface $product): float
    {
        $finalPrice = $product->getPriceInfo()->getPrice('final_price');
        $amount = $this->inclusive($product, $finalPrice->getAmount());

        if ($amount <= 0.0 && $product->getTypeId() === 'bundle') {
            $amount = $this->inclusive($product, $finalPrice->getMinimalPrice());
        }

        if ($amount <= 0.0) {
            $amount = (float)$this->taxHelper->getTaxPrice($product, (float)$product->getFinalPrice(), true);
        }

        return $amount;
    }

    /**
     * @param ProductInterface $product
     * @param \Magento\Framework\Pricing\Amount\AmountInterface $amount
     * @return float
     */
    private function inclusive(ProductInterface $product, $amount): float
    {
        if ($this->taxConfig->getPriceDisplayType() == TaxConfig::DISPLAY_TYPE_EXCLUDING_TAX) {
            return (float)$this->taxHelper->getTaxPrice($product, (float)$amount->getBaseAmount(), true);
        }

        return (float)$amount->getValue();
    }
}
