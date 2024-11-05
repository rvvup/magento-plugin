<?php

namespace Rvvup\Payments\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\Payment;
use Rvvup\Payments\Gateway\Method;

class PaymentSessionService
{
    /**
     * @var QuotePreparationService
     */
    private $quotePreparationService;

    /**
     * @var Payment
     */
    private $paymentResource;
    /** @var RvvupRestApi */
    private $rvvupApi;

    public function __construct(
        QuotePreparationService     $quotePreparationService,
        Payment                     $paymentResource,
        RvvupRestApi $rvvupApi
    )
    {
        $this->quotePreparationService = $quotePreparationService;
        $this->paymentResource = $paymentResource;
        $this->rvvupApi = $rvvupApi;
    }

    /**
     * @throws LocalizedException
     * @throws \Exception
     */
    public function create(Quote $quote, string $checkoutId): ?array
    {
        $quote = $this->quotePreparationService->prepare($quote);
        $discountTotal = $quote->getBaseSubtotal() - $quote->getBaseSubtotalWithDiscount();
        $taxTotal = $quote->getTotals()['tax']->getValue();
        $taxTotal = is_float($taxTotal) ? $taxTotal : 0.0;
        $currency = $quote->getQuoteCurrencyCode();
        $payment = $quote->getPayment();
        $method = str_replace(Method::PAYMENT_TITLE_PREFIX, '', $payment->getMethod());
        $captureType = $payment->getMethodInstance()->getCaptureType();
        if ($captureType != 'MANUAL') {
            $captureType = 'AUTOMATIC_PLUGIN';
        }
        $storeId = (string)$quote->getStoreId();
        $paymentSessionInput = [
            "sessionKey" => uniqid("rvvup"),
            "externalReference" => $quote->getReservedOrderId(),
            "total" => $this->buildAmount($quote->getGrandTotal(), $currency),
            "items" => $this->buildItems($quote),
            "customer" => $this->buildCustomer($quote),
            "billingAddress" => $this->buildAddress($quote->getBillingAddress()),
            "requiresShipping" => !$quote->getIsVirtual(),
            "paymentMethod" => $method,
            "captureType" => $captureType,
        ];

        if ($discountTotal > 0) {
            $paymentSessionInput["discountTotal"] = $this->buildAmount($discountTotal, $currency);
        }
        if ($taxTotal > 0) {
            $paymentSessionInput["taxTotal"] = $this->buildAmount($taxTotal, $currency);
        }
        if ($paymentSessionInput["requiresShipping"]) {
            $paymentSessionInput["shippingAddress"] = $this->buildAddress($quote->getShippingAddress());
        }

        $result = $this->rvvupApi->createPaymentSession($storeId, $checkoutId, $paymentSessionInput);

        $payment->setAdditionalInformation(Method::PAYMENT_ID, $result['payments'][0]['id']);
        $this->paymentResource->save($payment);

        return $result;
    }


    private function buildAmount(float $amount, string $currency): array
    {
        $formattedAmount = number_format($amount, 2, '.', '');
        return [
            "amount" => $formattedAmount,
            "currency" => $currency
        ];
    }

    private function buildItems(Quote $quote): array
    {
        $items = $quote->getAllVisibleItems();
        $currency = $quote->getQuoteCurrencyCode();
        $returnItems = [];

        /** @var \Magento\Quote\Api\Data\CartItemInterface $item */
        foreach ($items as $item) {
            $quantity = number_format($item->getQty(), 0, '.', '');
            $tax = $item->getPriceInclTax() - $item->getPrice();

            $itemData = [
                "sku" => $item->getSku(),
                "name" => $item->getName(),
                "price" => $this->buildAmount($item->getPrice(), $currency),
                "priceWithTax" => $this->buildAmount($item->getPriceInclTax(), $currency),
                "quantity" => $quantity,
                "total" => $this->buildAmount($item->getRowTotal(), $currency),
            ];

            if ($tax > 0) {
                $itemData["tax"] = $this->buildAmount($tax, $currency);
            }
            $product = $item->getProduct();

            if ($product !== null) {
                $itemData['restriction'] = $product->getData('rvvup_restricted') ? 'RESTRICTED' : 'ALLOWED';
            }

            $returnItems[] = $itemData;
        }

        return $returnItems;
    }

    private function buildCustomer(Quote $quote): ?array
    {
        $billingAddress = $quote->getBillingAddress();

        if ($billingAddress->getFirstname() !== null || $billingAddress->getLastname() !== null) {
            $email = $billingAddress->getEmail() ?: $quote->getCustomerEmail();
            return [
                'givenName' => $billingAddress->getFirstname(),
                'surname' => $billingAddress->getLastname(),
                'phoneNumber' => $billingAddress->getTelephone(),
                'email' => $email,
            ];
        }

        return [
            'givenName' => $quote->getCustomerFirstName(),
            'surname' => $quote->getCustomerLastName(),
            'email' => $quote->getCustomerEmail(),
        ];
    }

    private function buildAddress(AddressInterface $address): array
    {
        $line2 = $address->getStreetLine(2);
        $address = [
            "name" => $address->getName(),
            "phoneNumber" => $address->getTelephone(),
            "company" => $address->getCompany(),
            "line1" => $address->getStreetLine(1),
            "city" => $address->getCity(),
            "state" => $address->getRegionCode(),
            "postcode" => $address->getPostcode(),
            "countryCode" => $address->getCountryId(),
        ];
        if (!empty($line2)) {
            $address["line2"] = $line2;
        }
        return $address;
    }
}
