<?php

declare(strict_types=1);

namespace Rvvup\Payments\Service\Express;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Rvvup\Payments\Model\Shipping\ShippingMethod;
use Rvvup\Payments\Service\Shipping\ShippingMethodService;

class ExpressPaymentRequestMapper
{

    /** @var ShippingMethodService */
    private $shippingMethodService;

    /**
     * @param ShippingMethodService $shippingMethodService
     */
    public function __construct(ShippingMethodService $shippingMethodService)
    {
        $this->shippingMethodService = $shippingMethodService;
    }

    /**
     * @param Quote $quote
     * @return array returns object that can be passed into the Rvvup Sdk for Express Payment
     */
    public function map(Quote $quote): array
    {
        $total = $quote->getGrandTotal();
        $result = [
            'methodOptions' => [
                'APPLE_PAY' => $this->getApplePayOptions($quote)
            ],
            'total' => [
                'amount' => is_numeric($total) ? number_format((float)$total, 2, '.', '') : $total,
                'currency' => $quote->getQuoteCurrencyCode()
            ],
            'billing' => $this->mapAddress($quote->getBillingAddress()),
            'shipping' => $this->mapShippingAddress($quote),
        ];
        $result['shippingMethods'] = $this->getShippingMethods($quote, $result['shipping'] !== null);

        // If methods are empty, need to choose a new address in the express sheet
        if (empty($result['shippingMethods'])) {
            $result['shipping'] = null;
        } else {
            /*
            If the user didn't pick a shipping method, drop the shipping postcode. This forces the user to confirm their
            shipping address in the Apple Pay sheet. Which in turn sends the appropriate events from apple to the
            frontend, and we can keep the quotes in sync at this point.

            We also can't just default to the first method ourselves since that would force a preselection on page load.
            Also, no heavy work can run on Apple Pay button click — the browser only allows direct user actions there
            */
            $hasSelected = in_array(true, array_column($result['shippingMethods'], 'selected'), true);
            if (!$hasSelected) {
                if (isset($result['shipping']['address'])) {
                    $result['shipping']['address']['postcode'] = null;
                }
            }
        }
        return $result;
    }

    /**
     * @param Quote $quote
     * @param bool $hasShippingAddress
     * @return array|null
     */
    private function getShippingMethods(Quote $quote, bool $hasShippingAddress): ?array
    {
        if ($quote->isVirtual()) {
            return null;
        }
        // If address is not present then shipping methods will appear after the address update
        if (!$hasShippingAddress) {
            return null;
        }
        $availableMethods = $this->shippingMethodService->getAvailableShippingMethods($quote);
        $shippingMethods = $this->mapShippingMethods($availableMethods);
        if (empty($shippingMethods)) {
            return null;
        }

        $selectedMethod = $quote->getShippingAddress()->getShippingMethod();
        if (!empty($selectedMethod)) {
            $numShippingMethods = count($shippingMethods);
            for ($i = 0; $i < $numShippingMethods; $i++) {
                if ($shippingMethods[$i]['id'] === $selectedMethod) {
                    $shippingMethods[$i]['selected'] = true;
                    break;
                }
            }
        }
        return $shippingMethods;
    }

    /**
     * @param ShippingMethod[] $shippingMethods
     * @return array
     */
    public function mapShippingMethods(array $shippingMethods): array
    {
        return array_reduce($shippingMethods, function ($carry, $method) {
            $carry[] = [
                'id' => $method->getId(),
                'label' => $method->getLabel(),
                'amount' => ['amount' => $method->getAmount(), 'currency' => $method->getCurrency()],
            ];
            return $carry;
        }, []);
    }

    /**
     * @param Quote $quote
     * @return array|array[]
     */
    private function getApplePayOptions(Quote $quote): array
    {
        $options = [
            'paymentRequest' => [
                'requiredBillingContactFields' => ['postalAddress', 'name', 'email', 'phone'],
                // Apple quirk - We need these "shipping" fields to fill the billing email and phone
                'requiredShippingContactFields' => ['email', 'phone']
            ],
        ];
        if (!$quote->isVirtual()) {
            $options['paymentRequest']['requiredShippingContactFields'] = ['postalAddress', 'name', 'email', 'phone'];
            $options['paymentRequest']['shippingType'] = 'shipping';
            $options['paymentRequest']['shippingContactEditingMode'] = 'available';
        }

        return $options;
    }

    /**
     * @param Address $quoteAddress
     * @return array[]
     */
    private function mapAddress(Quote\Address $quoteAddress): ?array
    {
        // We ignore country code because it's always pre-selected by magento.
        // We also ignore region, city, postcode because apple partially sets this, if you cancel the sheet after a
        // address change. We only pre-fill the apple sheet when the user has actively entered the other fields.
        if ((!empty($quoteAddress->getStreet()) && !empty($quoteAddress->getStreet()[0])) ||
            !empty($quoteAddress->getFirstname()) ||
            !empty($quoteAddress->getLastname()) ||
            !empty($quoteAddress->getEmail()) ||
            !empty($quoteAddress->getTelephone())
        ) {
            return [
                'address' => [
                    'addressLines' => $quoteAddress->getStreet(),
                    'city' => $quoteAddress->getCity(),
                    'countryCode' => $quoteAddress->getCountryId(),
                    'postcode' => $quoteAddress->getPostcode(),
                    'state' => $quoteAddress->getRegion()
                ],
                'contact' => [
                    'givenName' => $quoteAddress->getFirstname(),
                    'surname' => $quoteAddress->getLastname(),
                    'email' => $quoteAddress->getEmail(),
                    'phoneNumber' => $quoteAddress->getTelephone()
                ]
            ];
        }

        return null;
    }

    /**
     * @param Quote $quote
     * @return void
     */
    private function mapShippingAddress(Quote $quote): ?array
    {
        if ($quote->isVirtual()) {
            return null;
        }
        $quoteShippingAddress = $quote->getShippingAddress();
        return $this->mapAddress($quoteShippingAddress);
    }
}
