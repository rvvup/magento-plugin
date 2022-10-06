<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Framework\UrlInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Rvvup\Payments\Exception\QuoteValidationException;

class OrderDataBuilder
{
    /** @var \Rvvup\Payments\Model\ConfigInterface */
    private $config;
    /** @var \Magento\Framework\UrlInterface */
    private $urlBuilder;

    /**
     * @param \Rvvup\Payments\Model\ConfigInterface $config
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @return void
     */
    public function __construct(ConfigInterface $config, UrlInterface $urlBuilder)
    {
        $this->config = $config;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @return array
     * @throws QuoteValidationException
     */
    public function build(CartInterface $quote): array
    {
        $discountTotal = $this->toCurrency($quote->getBaseSubtotal() - $quote->getBaseSubtotalWithDiscount());
        $taxTotal = $this->toCurrency($quote->getTotals()['tax']->getValue());
        $currencyCode = $quote->getQuoteCurrencyCode();

        $billingAddress = $quote->getBillingAddress();

        if ($billingAddress === null) {
            $this->throwException('Billing Address is always required');
        }

        $orderDataArray = [
            "externalReference" => $quote->getReservedOrderId(),
            "merchant" => [
                "id" => $this->config->getMerchantId(),
            ],
            "redirectToStoreUrl" => $this->urlBuilder->getUrl('rvvup/redirect/in'),
            "total" => [
                "amount" => $this->toCurrency($quote->getGrandTotal()),
                "currency" => $currencyCode,
            ],
            "discountTotal" => [
                "amount" => $discountTotal,
                "currency" => $currencyCode,
            ],
            "shippingTotal" => [
                "amount" => '0.00', // Default to 0.00.
                "currency" => $currencyCode,
            ],
            "taxTotal" => [
                "amount" => $taxTotal,
                "currency" => $currencyCode,
            ],
            "items" => $this->renderItems($quote),
            'customer' => [
                "givenName" => $billingAddress->getFirstname() ?? $quote->getCustomerFirstName(),
                "surname" => $billingAddress->getLastname() ?? $quote->getCustomerLastName(),
                "phoneNumber" => $billingAddress->getTelephone(),
                "email" => $billingAddress->getEmail() ?? $quote->getCustomerEmail(),
            ],
            'billingAddress' => $this->renderAddress($quote->getBillingAddress()),
            'requiresShipping' => false // Default to false.
        ];

        $orderDataArray['method'] = str_replace('rvvup_', '', $quote->getPayment()->getMethod());

        // We do not require shipping data for virtual orders (orders without tangible items).
        if ($quote->getIsVirtual()) {
            return $orderDataArray;
        }

        $shippingAddress = $quote->getShippingAddress();

        if ($shippingAddress === null) {
            $this->throwException('Shipping Address is required for this order');
        }

        // As we have tangible products, the order will require shipping.
        $orderDataArray['requiresShipping'] = true;
        $orderDataArray['shippingTotal']['amount'] = $this->toCurrency($shippingAddress->getShippingAmount());
        $orderDataArray['shippingAddress'] = $this->renderAddress($quote->getShippingAddress());

        return $orderDataArray;
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @return array
     */
    private function renderItems(CartInterface $quote): array
    {
        $items = $quote->getAllVisibleItems();
        $currency = $quote->getQuoteCurrencyCode();
        $built = [];

        /** @var \Magento\Quote\Api\Data\CartItemInterface $item */
        foreach ($items as $item) {
            $itemData = [
                "sku" => $item->getSku(),
                "name" => $item->getName(),
                "price" => [
                    "amount" => $this->toCurrency($item->getPrice()),
                    "currency" => $currency,
                ],
                "quantity" => $this->toQty($item->getQty()),
                "total" => [
                    "amount" => $this->toCurrency($item->getRowTotal()),
                    "currency" => $currency,
                ],
            ];

            $product = $item->getProduct();

            if ($product !== null) {
                $itemData['restriction'] = $product->getData('rvvup_restricted') ? 'RESTRICTED' : 'ALLOWED';
            }

            $built[] = $itemData;
        }

        return $built;
    }

    /**
     * @param \Magento\Quote\Api\Data\AddressInterface $address
     * @return array
     * @throws \Rvvup\Payments\Exception\QuoteValidationException
     */
    private function renderAddress(AddressInterface $address): array
    {
        if ($address->getPostcode() === null) {
            $this->throwException("Postcode missing from {$address->getAddressType()} address"); //@phpstan-ignore-line
        }

        return [
            "name" => $address->getName() ?? '',
            "phoneNumber" => $address->getTelephone() ?? '',
            "company" => $address->getCompany() ?? '',
            "line1" => $address->getStreetLine(1) ?? '',
            "line2" => $address->getStreetLine(2) ?? '',
            "city" => $address->getCity() ?? '',
            "state" => $address->getRegionCode() ?? '',
            "postcode" => $address->getPostcode(),
            "countryCode" => $address->getCountryId() ?? '',
        ];
    }

    /**
     * @param float $amount
     * @return string
     */
    private function toCurrency($amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * @param float $qty
     * @return string
     */
    private function toQty($qty): string
    {
        return number_format((float) $qty, 0, '.', '');
    }

    /**
     * @param string $error
     * @return void
     * @throws \Rvvup\Payments\Exception\QuoteValidationException
     */
    private function throwException(string $error): void
    {
        throw new QuoteValidationException(__($error));
    }
}
