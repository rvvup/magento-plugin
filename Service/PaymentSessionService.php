<?php

namespace Rvvup\Payments\Service;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlFactory;
use Magento\Framework\UrlInterface;
use Magento\Framework\Validator\Exception;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\Payment;
use Rvvup\Api\Model\AddressInput;
use Rvvup\Api\Model\CustomerInput;
use Rvvup\Api\Model\ItemInput;
use Rvvup\Api\Model\ItemRestriction;
use Rvvup\Api\Model\MoneyInput;
use Rvvup\Api\Model\PaymentSessionCreateInput;
use Rvvup\ApiException;
use Rvvup\Payments\Model\Data\PaymentSession;
use Rvvup\Payments\Controller\Redirect\In;
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

    /** @var ApiProvider */
    private $apiProvider;

    /** @var UrlFactory */
    protected $urlFactory;

    /**
     * @param QuotePreparationService $quotePreparationService
     * @param Payment $paymentResource
     * @param ApiProvider $apiProvider
     * @param UrlFactory $urlFactory
     */
    public function __construct(
        QuotePreparationService     $quotePreparationService,
        Payment                     $paymentResource,
        ApiProvider $apiProvider,
        UrlFactory $urlFactory
    ) {
        $this->quotePreparationService = $quotePreparationService;
        $this->paymentResource = $paymentResource;
        $this->apiProvider = $apiProvider;
        $this->urlFactory = $urlFactory;
    }

    /**
     * @param Quote $quote
     * @param string $checkoutId
     * @return PaymentSession
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws Exception
     */
    public function create(Quote $quote, string $checkoutId): PaymentSession
    {
        $this->quotePreparationService->validate($quote);
        $quote = $this->quotePreparationService->prepare($quote);

        $storeId = (string)$quote->getStoreId();

        $paymentSessionInput = $this->buildPaymentSession($checkoutId, $quote);

        $result = $this->apiProvider->getSdk($storeId)->paymentSessions()->create($checkoutId, $paymentSessionInput);

        $payment = $quote->getPayment();
        $payment->setAdditionalInformation(Method::ORDER_ID, $result['id']);
        $payment->setAdditionalInformation(Method::PAYMENT_ID, $result['payments'][0]['id']);
        $payment->setAdditionalInformation(Method::TRANSACTION_ID, $result['id']);

        $this->paymentResource->save($payment);

        $paymentSession = new PaymentSession();
        $paymentSession->setPaymentSessionId($result["id"]);
        $url = $this->urlFactory->create();
        $url->setQueryParam(In::PARAM_RVVUP_ORDER_ID, $result["id"]);
        $paymentSession->setRedirectUrl($url->getUrl('rvvup/redirect/in'));
        return $paymentSession;
    }

    /**
     * @param float $amount
     * @param string $currency
     * @return MoneyInput
     */
    private function buildAmount(float $amount, string $currency): MoneyInput
    {
        return (new MoneyInput())
            ->setAmount(number_format($amount, 2, '.', ''))
            ->setCurrency($currency);
    }

    /**
     * @param Quote $quote
     * @return array
     */
    private function buildItems(Quote $quote): array
    {
        $items = $quote->getAllVisibleItems();
        $currency = $quote->getQuoteCurrencyCode();
        $returnItems = [];

        /** @var CartItemInterface $item */
        foreach ($items as $item) {
            $quantity = number_format($item->getQty(), 0, '.', '');
            $tax = $item->getPriceInclTax() - $item->getPrice();

            $itemData = new ItemInput();
            $itemData
                ->setSku($item->getSku())
                ->setName($item->getName())
                ->setPrice($this->buildAmount($item->getPrice(), $currency))
                ->setPriceWithTax($this->buildAmount($item->getPriceInclTax(), $currency))
                ->setQuantity($quantity)
                ->setTotal($this->buildAmount($item->getRowTotal(), $currency));

            if ($tax > 0) {
                $itemData->setTax($this->buildAmount($tax, $currency));
            }
            $product = $item->getProduct();

            if ($product !== null) {
                $itemData->setRestriction(
                    $product->getData('rvvup_restricted') ? ItemRestriction::RESTRICTED : ItemRestriction::ALLOWED
                );
            }

            $returnItems[] = $itemData;
        }

        return $returnItems;
    }

    /**
     * @param Quote $quote
     * @return array|null
     */
    private function buildCustomer(Quote $quote): ?CustomerInput
    {
        $billingAddress = $quote->getBillingAddress();

        if ($billingAddress->getFirstname() !== null || $billingAddress->getLastname() !== null) {
            $email = $billingAddress->getEmail() ?: $quote->getCustomerEmail();
            return (new CustomerInput())
                ->setGivenName($billingAddress->getFirstname())
                ->setSurname($billingAddress->getLastname())
                ->setPhoneNumber($billingAddress->getTelephone())
                ->setEmail($email);
        }
        return (new CustomerInput())
            ->setGivenName($quote->getCustomerFirstName())
            ->setSurname($quote->getCustomerLastName())
            ->setEmail($quote->getCustomerEmail());
    }

    /**
     * @param AddressInterface $address
     * @return AddressInput
     */
    private function buildAddress(AddressInterface $address): AddressInput
    {
        $line2 = $address->getStreetLine(2);
        $state = $address->getRegionCode();
        $company = $address->getCompany();

        $addressInput = new AddressInput();
        $addressInput
            ->setName($address->getName())
            ->setPhoneNumber($address->getTelephone())
            ->setLine1($address->getStreetLine(1))
            ->setCity($address->getCity())
            ->setPostcode($address->getPostcode())
            ->setCountryCode($address->getCountryId());

        if (!empty($company)) {
            $addressInput->setCompany($company);
        }
        if (!empty($line2)) {
            $addressInput->setCompany($line2);
        }
        if (!empty($state)) {
            $addressInput->setState($state);
        }
        return $addressInput;
    }

    /**
     * @param string $checkoutId
     * @param Quote $quote
     * @return PaymentSessionCreateInput
     */
    private function buildPaymentSession(string $checkoutId, Quote $quote): PaymentSessionCreateInput
    {
        $discountTotal = $quote->getBaseSubtotal() - $quote->getBaseSubtotalWithDiscount();
        $taxTotal = $quote->getTotals()['tax']->getValue();
        $taxTotal = is_float($taxTotal) ? $taxTotal : 0.0;
        $currency = $quote->getQuoteCurrencyCode();
        $payment = $quote->getPayment();
        $method = str_replace(Method::PAYMENT_TITLE_PREFIX, '', $quote->getPayment()->getMethod());
        $captureType = $payment->getMethodInstance()->getCaptureType();

        if ($captureType != 'MANUAL') {
            $captureType = 'AUTOMATIC_PLUGIN';
        }
        $paymentSessionInput = new PaymentSessionCreateInput();
        $secureBaseUrl = $quote->getStore()->getBaseUrl(
            UrlInterface::URL_TYPE_WEB,
            true
        );
        $paymentSessionInput
            ->setSessionKey("$checkoutId." . $quote->getReservedOrderId())
            ->setExternalReference($quote->getReservedOrderId())
            ->setTotal($this->buildAmount($quote->getGrandTotal(), $currency))
            ->setItems($this->buildItems($quote))
            ->setCustomer($this->buildCustomer($quote))
            ->setBillingAddress($this->buildAddress($quote->getBillingAddress()))
            ->setRequiresShipping(!$quote->getIsVirtual())
            ->setPaymentMethod($method)
            ->setPaymentCaptureType($captureType)
            ->setMetadata([
                "domain" => $secureBaseUrl
            ]);

        if ($discountTotal > 0) {
            $paymentSessionInput->setDiscountTotal($this->buildAmount($discountTotal, $currency));
        }
        if ($taxTotal > 0) {
            $paymentSessionInput->setTaxTotal($this->buildAmount($taxTotal, $currency));
        }
        if ($paymentSessionInput->getRequiresShipping() === true) {
            $paymentSessionInput->setShippingAddress($this->buildAddress($quote->getShippingAddress()));
            $shippingAmount = $quote->getShippingAddress()->getShippingAmount();
            if ($shippingAmount > 0) {
                $paymentSessionInput->setShippingTotal($this->buildAmount($shippingAmount, $currency));
            }
        }
        return $paymentSessionInput;
    }
}
