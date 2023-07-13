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
}
