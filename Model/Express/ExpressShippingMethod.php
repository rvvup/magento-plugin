<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model\Express;

use Magento\Quote\Api\Data\ShippingMethodInterface;

class ExpressShippingMethod
{

    /** @var string */
    private $id;

    /** @var string */
    private $label;

    /** @var string */
    private $amount;

    /** @var string */
    private $currency;

    /**
     * @param ShippingMethodInterface $shippingMethod
     * @param string $currency
     */
    public function __construct(ShippingMethodInterface $shippingMethod, string $currency)
    {
        // Magento sets the shipping method using this format: `carrier_code`_`method_code`.
        $this->id = $shippingMethod->getCarrierCode() . '_' . $shippingMethod->getMethodCode();
        $this->label = $this->generateLabel($shippingMethod);
        $this->amount = number_format($shippingMethod->getPriceInclTax(), 2, '.', '');
        $this->currency = $currency;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return string
     */
    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    private function generateLabel(ShippingMethodInterface $shippingMethod): string
    {
        if ($shippingMethod->getCarrierTitle() === null && $shippingMethod->getMethodTitle() === null) {
            return $shippingMethod->getCarrierCode() . '_' . $shippingMethod->getMethodCode();
        }
        $label = ($shippingMethod->getCarrierTitle() ?? '');
        if ($shippingMethod->getCarrierTitle() && $shippingMethod->getMethodTitle()) {
            $label .= ': ';
        }
        $label .= $shippingMethod->getMethodTitle() ?? '';
        return $label;
    }

}
