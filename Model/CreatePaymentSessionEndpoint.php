<?php

declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Laminas\Http\Request;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\QuoteRepository;
use Rvvup\Payments\Api\CreatePaymentSessionInterface;
use Rvvup\Payments\Exception\QuoteValidationException;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Config\RvvupConfigurationInterface;
use Rvvup\Payments\Sdk\Curl;
use Rvvup\Payments\Service\Hash;

class CreatePaymentSessionEndpoint implements CreatePaymentSessionInterface
{
    /**
     * @var \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;
    /** @var QuoteRepository */
    private $quoteRepository;

    /** @var Hash */
    private $hashService;
    /** @var SerializerInterface */
    private $json;

    /** @var Curl */
    private $curl;

    /** @var RvvupConfigurationInterface */
    private $config;

    /**
     * @param \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @return void
     */
    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        QuoteRepository                 $quoteRepository,
        Hash                            $hashService,
        SerializerInterface             $json,
        Curl                            $curl,
        RvvupConfigurationInterface     $config

    )
    {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->quoteRepository = $quoteRepository;
        $this->hashService = $hashService;
        $this->json = $json;
        $this->curl = $curl;
        $this->config = $config;
    }

    /**
     * Get the payment actions for the masked cart ID.
     *
     * @param string $cartId
     * @return string
     */
    public function execute(string $cartId, string $checkoutId): string
    {
        $quoteId = (string)$this->maskedQuoteIdToQuoteId->execute($cartId);
        $quote = $this->quoteRepository->get($quoteId);
//        $this->ensureCustomerEmailExists($quote);
//        if (!$quote->getCustomerEmail()) {
//            throw new InputException(__('Missing email address'));
//        }
        $quote->reserveOrderId();
        $this->quoteRepository->save($quote);
        $this->hashService->saveQuoteHash($quote);
        $payment = $quote->getPayment();
        $method = str_replace(Method::PAYMENT_TITLE_PREFIX, '', $payment->getMethod());

        $storeId = (string)$quote->getStoreId();
        $billingAddress = $quote->getBillingAddress();

        $discountTotal = $quote->getBaseSubtotal() - $quote->getBaseSubtotalWithDiscount();
        $taxTotal = $quote->getTotals()['tax']->getValue();
        $orderDataArray = [
            "sessionKey" => time() . "",
            "externalReference" => $quote->getReservedOrderId(),
//            "items" => $this->renderItems($quote),
            "total" => [
                "amount" => $this->toCurrency($quote->getGrandTotal()),
                "currency" => $quote->getQuoteCurrencyCode(),
            ],
            "requiresShipping" => !$quote->getIsVirtual(),
            "paymentMethod" => $method,
            "paymentCaptureType" => "AUTOMATIC_PLUGIN",
        ];
        $orderDataArray['customer'] = $this->renderCustomer($quote, $billingAddress);
        $orderDataArray['billingAddress'] = $this->renderBillingAddress($quote, $billingAddress);
        $orderDataArray['shippingAddress'] = $this->renderShippingAddress($quote);

        if ($discountTotal > 0) {
            $orderDataArray['discountTotal'] = [
                "amount" => $this->toCurrency($discountTotal),
                "currency" => $quote->getQuoteCurrencyCode(),
            ];
        }
        if ($taxTotal > 0) {
            $orderDataArray['taxTotal'] = [
                "amount" => $this->toCurrency($taxTotal),
                "currency" => $quote->getQuoteCurrencyCode(),
            ];
        }
        $token = $this->config->getBearerToken($storeId);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ];

        $request = $this->curl->request(Request::METHOD_POST, $this->getApiUrl($storeId) . "/$checkoutId/payment-sessions", [
            'headers' => $headers,
            'json' => $orderDataArray
        ]);
        $body = $this->json->unserialize($request->body);
        return $body['id'] ?? '';
    }

    private function renderCustomer($quote, $billingAddress = null
    ): ?array
    {
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

    private function renderShippingAddress($quote): ?array
    {
        return  $this->renderAddress($quote->getShippingAddress());
    }

    private function renderBillingAddress(
        $quote,
        $billingAddress = null
    ): ?array
    {
        return $billingAddress !== null ? $this->renderAddress($quote->getBillingAddress()) : null;
    }

    private function renderAddress($address): array
    {
        if ($address->getPostcode() === null) {
            $this->throwException("Postcode missing from {$address->getAddressType()} address"); //@phpstan-ignore-line
        }

        $addressOut = [
            "name" => $address->getName() ?? '',
            "phoneNumber" => $address->getTelephone() ?? '',
            "line1" => $address->getStreetLine(1) ?? '',
            "city" => $address->getCity() ?? '',
            "postcode" => $address->getPostcode(),
            "countryCode" => $address->getCountryId() ?? '',
        ];
        if ($address->getStreetLine(2)) {
            $addressOut['line2'] = $address->getStreetLine(2);
        }
        return $addressOut;
    }

    /**
     * @param string $error
     * @return void
     * @throws QuoteValidationException
     */
    private function throwException(string $error): QuoteValidationException
    {
        throw new QuoteValidationException(__($error));
    }


    /**
     * @param string $amount
     * @return string
     */
    private function toCurrency($amount): string
    {
        return number_format((float)$amount, 2, '.', '');
    }

    private function getApiUrl(string $storeId): string
    {
        $merchantId = $this->config->getMerchantId($storeId);
        $baseUrl = $this->config->getRestApiUrl($storeId);
        return "$baseUrl/$merchantId/checkouts";
    }
}
