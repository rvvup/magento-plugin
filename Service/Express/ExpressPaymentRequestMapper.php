<?php

declare(strict_types=1);

namespace Rvvup\Payments\Service\Express;

use Magento\Checkout\Api\Data\ShippingInformationInterfaceFactory;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\ShipmentEstimationInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Rvvup\Payments\Model\Shipping\ShippingMethod;
use Rvvup\Payments\Service\Shipping\ShippingMethodService;

class ExpressPaymentRequestMapper
{

    /** @var ShipmentEstimationInterface */
    private $shipmentEstimation;

    /** @var CartRepositoryInterface */
    private $quoteRepository;

    /** @var ShippingInformationInterfaceFactory */
    private $shippingInformationFactory;

    /** @var ShippingInformationManagementInterface */
    private $shippingInformationManagement;

    /** @var ShippingMethodService */
    private $shippingMethodService;

    /**
     * @param ShipmentEstimationInterface $shipmentEstimation
     * @param CartRepositoryInterface $quoteRepository
     * @param ShippingInformationInterfaceFactory $shippingInformationFactory
     * @param ShippingInformationManagementInterface $shippingInformationManagement
     * @param ShippingMethodService $shippingMethodService
     */
    public function __construct(
        ShipmentEstimationInterface            $shipmentEstimation,
        CartRepositoryInterface                $quoteRepository,
        ShippingInformationInterfaceFactory    $shippingInformationFactory,
        ShippingInformationManagementInterface $shippingInformationManagement,
        ShippingMethodService                  $shippingMethodService
    ) {
        $this->shipmentEstimation = $shipmentEstimation;
        $this->quoteRepository = $quoteRepository;
        $this->shippingInformationFactory = $shippingInformationFactory;
        $this->shippingInformationManagement = $shippingInformationManagement;
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
            'shipping' => $this->mapShippingAddress($quote)
        ];

        if (!$quote->isVirtual()) {

            // If address is null then shipping methods will appear after the address update
            if ($result['shipping'] !== null) {
                $availableMethods = $this->shippingMethodService->getAvailableShippingMethods($quote);
                $shippingMethods = $this->mapShippingMethods($availableMethods);
                // If methods are empty, need to choose a new address in the express sheet
                if (empty($shippingMethods)) {
                    $result['shipping'] = null;
                } else {
                    $result['shippingMethods'] = $shippingMethods;
                    $selectedMethod = $quote->getShippingAddress()->getShippingMethod();
                    if (empty($selectedMethod)) {
                        $result['shippingMethods'][0]['selected'] = true;
                    } else {
                        $numShippingMethods = count($result['shippingMethods']);
                        for ($i = 0; $i < $numShippingMethods; $i++) {
                            if ($result['shippingMethods'][$i]['id'] === $selectedMethod) {
                                $result['shippingMethods'][$i]['selected'] = true;
                                break;
                            }
                        }
                    }
                }
            }
        }

        return $result;
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
