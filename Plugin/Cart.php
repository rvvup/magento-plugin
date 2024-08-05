<?php

namespace Rvvup\Payments\Plugin;

class Cart extends \Magento\Checkout\CustomerData\Cart
{
    /**
     * @param Cart $subject
     * @param array $result
     * @return array
     */
    public function afterGetSectionData(\Magento\Checkout\CustomerData\Cart $subject, array $result): array
    {
        $quote = $subject->getQuote();
        $result['quote_id'] = $quote->getId();
        return $result;
    }
}
