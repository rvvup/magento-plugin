<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\Validator\Exception;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\QuoteValidator;
use Magento\Quote\Model\ResourceModel\Quote\Payment;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Rvvup\Payments\Exception\QuoteValidationException;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Config\RvvupConfigurationInterface;
use Rvvup\Payments\Service\TaxRateCalculator;

class OrderDataBuilder
{
    /** @var AddressRepositoryInterface  */
    private $customerAddressRepository;

    /** @var UrlInterface  */
    private $urlBuilder;

    /** @var RvvupConfigurationInterface  */
    private $config;

    /** @var CartRepositoryInterface  */
    private $cartRepository;

    /** @var SearchCriteriaBuilder  */
    private $searchCriteriaBuilder;

    /** @var OrderRepositoryInterface  */
    private $orderRepository;

    /** @var Payment  */
    private $paymentResource;

    /** @var QuoteValidator */
    private $quoteValidator;

    /** @var TaxRateCalculator */
    private $taxRateCalculator;

    /**
     * @param AddressRepositoryInterface $customerAddressRepository
     * @param UrlInterface $urlBuilder
     * @param RvvupConfigurationInterface $config
     * @param CartRepositoryInterface $cartRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param QuoteValidator $quoteValidator
     * @param Payment $paymentResource
     * @param TaxRateCalculator $taxRateCalculator
     */
    public function __construct(
        AddressRepositoryInterface $customerAddressRepository,
        UrlInterface $urlBuilder,
        RvvupConfigurationInterface $config,
        CartRepositoryInterface $cartRepository,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        QuoteValidator $quoteValidator,
        Payment $paymentResource,
        TaxRateCalculator $taxRateCalculator
    ) {
        $this->customerAddressRepository = $customerAddressRepository;
        $this->urlBuilder = $urlBuilder;
        $this->config = $config;
        $this->cartRepository = $cartRepository;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->quoteValidator = $quoteValidator;
        $this->paymentResource = $paymentResource;
        $this->taxRateCalculator = $taxRateCalculator;
    }

    /**
     * @param CartInterface $quote
     * @param bool $express
     * @param bool $validate
     * @return array
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws QuoteValidationException
     * @throws Exception
     */
    public function build(CartInterface $quote, bool $express = false, bool $validate = true): array
    {
        $billingAddress = $quote->getBillingAddress();

        if ($validate) {
            $this->quoteValidator->validateBeforeSubmit($quote);
        }

        // Validate that billing address exists if this is NOT a request to build express payment data.
        if (!$express && $billingAddress === null) {
            $this->throwException('Billing Address is always required');
        }

        $orderDataArray = $this->renderBase($quote, $express);
        $orderDataArray['customer'] = $this->renderCustomer($quote, $express, $billingAddress);
        $orderDataArray['billingAddress'] = $this->renderBillingAddress($quote, $express, $billingAddress);

        // We do not require shipping data for virtual orders (orders without tangible items).
        if ($quote->getIsVirtual()) {
            return $orderDataArray;
        }

        $shippingAddress = $quote->getShippingAddress();

        // Validate that Shipping Address exists if this is NOT a request to build express payment data.
        if (!$express && $shippingAddress === null) {
            $this->throwException('Shipping Address is required for this order');
        }

        $orderDataArray['shippingAddress'] = $this->renderShippingAddress($quote, $express, $shippingAddress);

        $payment = $quote->getPayment();
        $payment->setAdditionalInformation(Method::CREATE_NEW, false);
        $this->paymentResource->save($payment);
        // As we have tangible products, the order will require shipping.
        $orderDataArray['shippingTotal']['amount'] = $this->toCurrency($shippingAddress->getShippingAmount());

        return $orderDataArray;
    }

    /**
     * @param string $orderId
     * @return array
     * @throws NoSuchEntityException
     * @throws QuoteValidationException
     */
    public function createInputForExpiredOrder(string $orderId): array
    {
        $quote = $this->getQuoteByOrderIncrementId($orderId);

        return $this->build($quote);
    }

    /**
     * @param string $orderId
     * @return CartInterface
     * @throws NoSuchEntityException
     */
    private function getQuoteByOrderIncrementId(string $orderId): CartInterface
    {
        $searchCriteria = $this->searchCriteriaBuilder->addFilter(OrderInterface::INCREMENT_ID, $orderId)->create();

        $quoteId = current($this->orderRepository->getList($searchCriteria)->getItems())->getQuoteId();

        return $this->cartRepository->get($quoteId);
    }

    /**
     * Get the base data, common for all Rvvup payment request types.
     *
     * @param CartInterface $quote
     * @param bool $express
     * @return array
     * @throws QuoteValidationException
     */
    private function renderBase(CartInterface $quote, bool $express = false): array
    {
        $payment = $quote->getPayment();
        $storeId = (string) $quote->getStoreId();

        // Validate the quote/order is paid via Rvvup.
        if ($payment === null
            || $payment->getMethod() === null
            || strpos($payment->getMethod(), Method::PAYMENT_TITLE_PREFIX) !== 0
        ) {
            $this->throwException('This order is not paid via Rvvup');
        }

        $orderDataArray = [
            "type" => 'V2',
            "externalReference" => $quote->getReservedOrderId(),
            "total" => [
                "amount" => $this->toCurrency($quote->getGrandTotal()),
                "currency" => $quote->getQuoteCurrencyCode(),
            ],
            "discountTotal" => [
                "amount" => $this->toCurrency($quote->getBaseSubtotal() - $quote->getBaseSubtotalWithDiscount()),
                "currency" => $quote->getQuoteCurrencyCode(),
            ],
            "shippingTotal" => [
                "amount" => '0.00', // Default to 0.00.
                "currency" => $quote->getQuoteCurrencyCode(),
            ],
            "taxTotal" => [
                "amount" => $this->toCurrency($quote->getTotals()['tax']->getValue()),
                "currency" => $quote->getQuoteCurrencyCode(),
            ],
            "requiresShipping" => !$quote->getIsVirtual(),
        ];

        if ($payment->getAdditionalInformation(Method::EXPRESS_PAYMENT_KEY)
        && !$payment->getAdditionalInformation(Method::CREATE_NEW)) {
            unset($orderDataArray["type"]);
            $orderDataArray['merchantId'] = $this->config->getMerchantId($storeId);
            $orderDataArray['express'] = true;
        } else {
            $orderDataArray["merchant"] = [
                "id" => $this->config->getMerchantId($storeId),
            ];
            $orderDataArray["redirectToStoreUrl"] = $this->urlBuilder->getUrl('rvvup/redirect/in');
            $orderDataArray["items"] = $this->renderItems($quote);
        }

        if ($id = $payment->getAdditionalInformation(Method::ORDER_ID)) {
            if (!$payment->getAdditionalInformation(Method::CREATE_NEW) &&
            !$payment->getAdditionalInformation(Method::EXPRESS_PAYMENT_KEY)
            ) {
                $payment->setAdditionalInformation(Method::CREATE_NEW, true);
                $this->paymentResource->save($payment);
                return $orderDataArray;
            }
            $orderDataArray['id'] = $id;
        };

        return $orderDataArray;
    }

    /**
     * @param CartInterface $quote
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
                "priceWithTax" => [
                    "amount" => $this->toCurrency($item->getPriceInclTax()),
                    "currency" => $currency,
                ],
                "tax" => [
                    "amount" => $this->toCurrency($item->getPriceInclTax() - $item->getPrice()),
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
                $taxRate = $this->taxRateCalculator->getItemTaxRate($quote, $product);
                if ($taxRate !== null) {
                    $itemData['taxRate'] = $taxRate;
                }
                $itemData['restriction'] = $product->getData('rvvup_restricted') ? 'RESTRICTED' : 'ALLOWED';
            }

            $built[] = $itemData;
        }

        return $built;
    }

    /**
     * @param CartInterface $quote
     * @param bool $express
     * @param AddressInterface|null $billingAddress
     * @return array|null
     */
    private function renderCustomer(
        CartInterface $quote,
        bool $express = false,
        ?AddressInterface $billingAddress = null
    ): ?array {
        // If we have an express payment and quote belongs to a customer, get customer data from customer object.
        if ($express && $quote->getCustomer() !== null && $quote->getCustomer()->getId() !== null) {
            $customerBillingAddress = $quote->getCustomer()->getDefaultBilling() !== null
                ? $this->renderCustomerAddress((int)$quote->getCustomer()->getDefaultBilling())
                : null;

            return [
                'givenName' => $quote->getCustomer()->getFirstname() ?? '',
                'surname' => $quote->getCustomer()->getLastname() ?? '',
                'phoneNumber' => $customerBillingAddress !== null ? $customerBillingAddress['phoneNumber'] : '',
                'email' => $quote->getCustomer()->getEmail() ?? '',
            ];
        }

        // Otherwise, if we have a billing address, use it to set customer data.
        if ($billingAddress !== null
            && ($billingAddress->getFirstname() !== null || $billingAddress->getLastname() !== null)
        ) {
            $email = $billingAddress->getEmail() ?: $quote->getCustomerEmail();
            return [
                'givenName' => $billingAddress->getFirstname() ?? '',
                'surname' => $billingAddress->getLastname() ?? '',
                'phoneNumber' => $billingAddress->getTelephone() ?? '',
                'email' => $email ?? '',
            ];
        }

        // If billing address null & we don't have quote data, return null.
        if ($quote->getCustomerFirstName() === null
            && $quote->getCustomerFirstName() === null
            && $quote->getCustomerEmail() === null
        ) {
            return null;
        }

        // Otherwise set the data.
        return [
            'givenName' => $quote->getCustomerFirstName() ?? '',
            'surname' => $quote->getCustomerLastName() ?? '',
            'phoneNumber' => '',
            'email' => $quote->getCustomerEmail() ?? '',
        ];
    }

    /**
     * @param CartInterface $quote
     * @param bool $express
     * @param AddressInterface|null $billingAddress
     * @return array|null
     * @throws QuoteValidationException
     */
    private function renderBillingAddress(
        CartInterface $quote,
        bool $express = false,
        ?AddressInterface $billingAddress = null
    ): ?array {
        // If not an express payment, return billing address data as normal.
        if (!$express) {
            return $billingAddress !== null ? $this->renderAddress($quote->getBillingAddress()) : null;
        }

        if ($express && $quote->getPayment()->getAdditionalInformation(Method::CREATE_NEW)) {
            return null;
        }

        if ($billingAddress !== null) {
            return $this->renderAddress($billingAddress);
        }

        // Otherwise, return customer billing address if full.
        return $this->renderCustomerAddress((int)$quote->getCustomer()->getDefaultBilling());
    }

    /**
     * @param CartInterface $quote
     * @param bool $express
     * @param AddressInterface|null $shippingAddress
     * @return array|null
     * @throws QuoteValidationException
     */
    private function renderShippingAddress(
        CartInterface $quote,
        bool $express = false,
        ?AddressInterface $shippingAddress = null
    ): ?array {
        // If not an express payment, return billing address data as normal.
        if (!$express) {
            return $shippingAddress !== null ? $this->renderAddress($quote->getShippingAddress()) : null;
        }

        if ($express && $quote->getPayment()->getAdditionalInformation(Method::CREATE_NEW)) {
            return null;
        }

        if ($shippingAddress !== null) {
            return $this->renderAddress($shippingAddress);
        }

        // Otherwise, return customer billing address if full.
        return $this->renderCustomerAddress((int)$quote->getCustomer()->getDefaultShipping());
    }

    /**
     * @param AddressInterface $address
     * @return array
     * @throws QuoteValidationException
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
     * Get a customers address data by the customer's address id.
     *
     * @param int $customerAddressId
     * @return array|null
     */
    private function renderCustomerAddress(int $customerAddressId): ?array
    {
        try {
            $address = $this->customerAddressRepository->getById($customerAddressId);

            // Return null if any required address data are missing.
            if ($address->getFirstname() === null
                || $address->getLastname() === null
                || $address->getStreet() === null
                || $address->getCity() === null
                || $address->getPostcode() === null
                || $address->getCountryId() === null
            ) {
                return null;
            }

            $customerName = [
                $address->getFirstname(),
                $address->getMiddlename() ?? '',
                $address->getLastname()
            ];

            $street = $address->getStreet();

            return [
                // Array filter removes empty values if no callback provided.
                'name' => implode(' ', array_filter(array_map('trim', $customerName))),
                'phoneNumber' => $address->getTelephone() ?? '',
                'company' => $address->getCompany() ?? '',
                // We already validate that street property is not null.
                'line1' => is_array($street) && isset($street[0]) ? $street[0] : '',
                'line2' => is_array($street) && isset($street[1]) ? $street[1] : '',
                'city' => $address->getCity() ?? '',
                'state' => $address->getRegion() !== null && $address->getRegion()->getRegion() !== null
                    ? $address->getRegion()->getRegion()
                    : '',
                'postcode' => $address->getPostcode(),
                'countryCode' => $address->getCountryId() ?? '',
            ];
        } catch (LocalizedException $ex) {
            return null;
        }
    }

    /**
     * @param float $amount
     * @return string
     */
    private function toCurrency($amount): string
    {
        return number_format((float)$amount, 2, '.', '');
    }

    /**
     * @param float $qty
     * @return string
     */
    private function toQty($qty): string
    {
        return number_format((float)$qty, 0, '.', '');
    }

    /**
     * @param string $error
     * @return void
     * @throws QuoteValidationException
     */
    private function throwException(string $error): void
    {
        throw new QuoteValidationException(__($error));
    }
}
