<?php declare(strict_types=1);

namespace Rvvup\Payments\Model;

use Laminas\Uri\Exception\InvalidArgumentException;
use Laminas\Uri\Exception\InvalidUriException;
use Laminas\Uri\UriFactory;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Api\AddressMetadataInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\CustomAttributesDataInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\View\Element\Template;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\ResourceModel\Quote\Address;
use Magento\Ui\Component\Form\Element\Multiline;
use Rvvup\Payments\Gateway\Method;
use Rvvup\Payments\Model\Clearpay\Config;
use Rvvup\Payments\Model\ConfigInterface as RvvupConfig;

class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var ConfigInterface|RvvupConfig
     */
    private $config;

    /**
     * @var \Rvvup\Payments\Model\SdkProxy
     */
    private $sdkProxy;

    /**
     * @var \Magento\Customer\Api\AddressMetadataInterface
     */
    private $addressMetadata;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * Set via etc/frontend/di.xml
     *
     * @var \Magento\Framework\Session\SessionManagerInterface|\Magento\Checkout\Model\Session\Proxy
     */
    private $checkoutSession;

    /**
     * Set via etc/frontend/di.xml
     *
     * @var \Magento\Framework\Session\SessionManagerInterface|\Magento\Customer\Model\Session\Proxy
     */
    private $customerSession;

    /**
     * @var \Magento\Framework\View\Element\Template
     */
    private $template;

    /**
     * @var \Magento\Quote\Api\Data\AddressInterfaceFactory
     */
    private $addressFactory;

    /**
     * @var \Magento\Quote\Model\ResourceModel\Quote\Address
     */
    private $addressResourceModel;

    /**
     * @var \Rvvup\Payments\Model\Clearpay\Config
     */
    private $clearpayConfig;

    /**
     * @param ConfigInterface|RvvupConfig $config
     * @param \Rvvup\Payments\Model\SdkProxy $sdkProxy
     * @param \Magento\Customer\Api\AddressMetadataInterface $addressMetadata
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param \Magento\Framework\Session\SessionManagerInterface $checkoutSession
     * @param \Magento\Framework\Session\SessionManagerInterface $customerSession
     * @param \Magento\Framework\View\Element\Template $template
     * @param \Magento\Quote\Api\Data\AddressInterfaceFactory $addressFactory
     * @param \Magento\Quote\Model\ResourceModel\Quote\Address $addressResourceModel
     * @param \Rvvup\Payments\Model\Clearpay\Config $clearpayConfig
     * @return void
     */
    public function __construct(
        RvvupConfig $config,
        SdkProxy $sdkProxy,
        AddressMetadataInterface $addressMetadata,
        CustomerRepositoryInterface $customerRepository,
        SessionManagerInterface $checkoutSession,
        SessionManagerInterface $customerSession,
        Template $template,
        AddressInterfaceFactory $addressFactory,
        Address $addressResourceModel,
        Config $clearpayConfig
    ) {
        $this->config = $config;
        $this->sdkProxy = $sdkProxy;
        $this->addressMetadata = $addressMetadata;
        $this->customerRepository = $customerRepository;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->template = $template;
        $this->addressFactory = $addressFactory;
        $this->addressResourceModel = $addressResourceModel;
        $this->clearpayConfig = $clearpayConfig;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getConfig()
    {
        if (!$this->config->isActive()) {
            return [];
        }

        $quote = $this->checkoutSession->getQuote();

        $methods = $this->sdkProxy->getMethods((string)  $quote->getGrandTotal(), $quote->getQuoteCurrencyCode());
        $items = [];

        foreach ($methods as $method) {
            $items[Method::PAYMENT_TITLE_PREFIX . $method['name']] = [
                'component' => 'Rvvup_Payments/js/view/payment/method-renderer/rvvup-method',
                'isBillingAddressRequired' => true,
                'description' => $method['description'],
                'logo' => $this->getLogo($method['name']),
                'summary_url' => $this->getSummaryUrl(
                    $this->isExpressPaymentCart($quote),
                    $method['summaryUrl'] ?? null
                ),
                'assets' => $method['assets'],
            ];

            if ($method['name'] == 'PAYPAL') {
                $items[Method::PAYMENT_TITLE_PREFIX . $method['name']]['style'] =
                    $this->config->getPaypalBlockStyling(ConfigInterface::XML_PATH_STYLE);

                $items[Method::PAYMENT_TITLE_PREFIX . $method['name']]['border'] =
                    $this->config->getPaypalBlockStyling(ConfigInterface::XML_PATH_BORDER_STYLING);

                $items[Method::PAYMENT_TITLE_PREFIX . $method['name']]['background'] =
                    $this->config->getPaypalBlockStyling(ConfigInterface::XML_PATH_BACKGROUND_STYLING);
            }
        }

        // We need to add the address data only if we have an express payment for logged in customers with addresses.
        // If customer has addresses, the default address book is used, so we need to pass our custom address data.
        if ($this->isGuest() || !$this->isExpressPaymentCart($quote) || !$this->hasCustomerAddresses()) {
            return ['payment' => $items];
        }

        return array_merge(
            ['payment' => $items],
            $this->getCartShippingAddressData($quote),
            $this->getCartBillingAddressData($quote)
        );
    }

    /**
     * @param string $code
     * @return string
     */
    private function getLogo(string $code): string
    {
        $base = 'Rvvup_Payments::images/%s.svg';
        switch ($code) {
            case 'YAPILY':
                $url = sprintf($base, 'yapily');
                break;
            case 'CLEARPAY':
                $theme = $this->clearpayConfig->getTheme();
                $url = sprintf($base, 'clearpay/' . $theme);
                break;
            case 'PAYPAL':
                $url = sprintf($base, 'paypal');
                break;
            case 'CARD':
                $url = sprintf($base, 'card');
                break;
            case 'FAKE_PAYMENT_METHOD':
            default:
                $url = sprintf($base, 'rvvup');
        }

        return $this->template->getViewFileUrl($url);
    }

    /**
     * Get the summary URL with the mode appended for express payments on checkout.
     *
     * @param bool $express
     * @param string|null $summaryUrl
     * @return string
     */
    private function getSummaryUrl(bool $express = false, ?string $summaryUrl = null): string
    {
        if ($summaryUrl === null) {
            return '';
        }

        if (!$express) {
            return $summaryUrl;
        }

        try {
            $url = UriFactory::factory($summaryUrl);
        } catch (InvalidArgumentException $ex) {
            return $summaryUrl;
        }

        // Add mode=express to query.
        $queryArray = $url->getQueryAsArray();
        $queryArray['mode'] = 'express';

        $url->setQuery($queryArray);

        try {
            return $url->toString();
        } catch (InvalidUriException $ex) {
            return $summaryUrl;
        }
    }

    /**
     * Check whether current cart has express payment data.
     *
     * @param \Magento\Quote\Api\Data\CartInterface $cart
     * @return bool
     */
    private function isExpressPaymentCart(CartInterface $cart): bool
    {
        return $cart->getPayment() !== null
            && $cart->getPayment()->getAdditionalInformation(Method::EXPRESS_PAYMENT_KEY) === true;
    }

    /**
     * @return bool
     */
    private function isGuest(): bool
    {
        return !$this->customerSession->isLoggedIn();
    }

    /**
     * Check whether current session customer has any addresses in their address book.
     *
     * @return bool
     */
    private function hasCustomerAddresses(): bool
    {
        if ($this->isGuest()) {
            return false;
        }

        try {
            $customer = $this->customerRepository->getById($this->customerSession->getCustomerId());
        } catch (NoSuchEntityException|LocalizedException $ex) {
            // no log required.
            return false;
        }

        return $customer->getAddresses() !== null && !empty($customer->getAddresses());
    }

    /**
     * Get cart shipping address data for checkout.
     *
     * For logged-in users, getShippingAddress returns the customer's default shipping address from the address book,
     * even though the quote_address ID is correctly set.
     * Hence, we load the address from the resource model by the address ID.
     *
     * @param \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote $cart
     * @return array
     */
    private function getCartShippingAddressData(CartInterface $cart): array
    {
        if ($cart->getShippingAddress() === null || !$cart->getShippingAddress()->getId()) {
            return [];
        }

        $shippingAddressFromData = $this->getAddressFromData($this->getCartAddressByAddressId(
            (int) $cart->getShippingAddress()->getId()
        ));

        if (empty($shippingAddressFromData)) {
            return [];
        }

        // Required so we can pick it up from JS mixin.
        $shippingAddressFromData["type"] = "new-customer-address";

        return [
            'isShippingAddressFromDataValid' => $cart->getShippingAddress()->validate() === true,
            'shippingAddressFromData' => $shippingAddressFromData
        ];
    }

    /**
     * Get cart billing address data for checkout.
     *
     * For logged-in users, getBillingAddress returns the customer's default billing address from the address book,
     * even though the quote_address ID is correctly set.
     * Hence, we load the address from the resource model by the address ID.
     *
     * @param \Magento\Quote\Api\Data\CartInterface|\Magento\Quote\Model\Quote $cart
     * @return array
     */
    private function getCartBillingAddressData(CartInterface $cart): array
    {
        if ($cart->getBillingAddress() === null || !$cart->getBillingAddress()->getId()) {
            return [];
        }

        $billingAddressFromData = $this->getAddressFromData($this->getCartAddressByAddressId(
            (int) $cart->getBillingAddress()->getId()
        ));

        if (empty($billingAddressFromData)) {
            return [];
        }

        // Required so we can pick it up from JS mixin.
        $billingAddressFromData["type"] = "new-customer-address";

        return [
            'isBillingAddressFromDataValid' => $cart->getBillingAddress()->validate() === true,
            'billingAddressFromData' => $billingAddressFromData
        ];
    }

    /**
     * @param int $addressId
     * @return \Magento\Quote\Api\Data\AddressInterface|\Magento\Quote\Model\Quote\Address
     */
    private function getCartAddressByAddressId(int $addressId)
    {
        /** @var \Magento\Quote\Api\Data\AddressInterface|\Magento\Quote\Model\Quote\Address $address */
        $address = $this->addressFactory->create();

        $this->addressResourceModel->load($address, $addressId);

        return $address;
    }

    /**
     * Create address data appropriate to fill checkout address form
     *
     * @param \Magento\Quote\Api\Data\AddressInterface $address
     * @return array
     */
    private function getAddressFromData(AddressInterface $address): array
    {
        if ($address->getEmail() === null || empty($address->getEmail())) {
            return [];
        }

        try {
            $attributesMetadata = $this->addressMetadata->getAllAttributesMetadata();
        } catch (LocalizedException $ex) {
            // Silent return.
            return [];
        }

        $addressData = [];

        foreach ($attributesMetadata as $attributeMetadata) {
            if (!$attributeMetadata->isVisible()) {
                continue;
            }

            $attributeCode = $attributeMetadata->getAttributeCode();
            $attributeData = $address->getData($attributeCode);

            if (!$attributeData) {
                continue;
            }

            if ($attributeMetadata->getFrontendInput() === Multiline::NAME) {
                $attributeData = is_array($attributeData) ? $attributeData : explode("\n", $attributeData);
                $attributeData = (object)$attributeData;
            }

            if ($attributeMetadata->isUserDefined()) {
                $addressData[CustomAttributesDataInterface::CUSTOM_ATTRIBUTES][$attributeCode] = $attributeData;
                continue;
            }

            $addressData[$attributeCode] = $attributeData;
        }

        return $addressData;
    }
}
