/**
 * Populating the billing and shipping addresses in the checkout
 */
define([
    'mage/utils/wrapper',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/action/select-shipping-address',
    'Magento_Checkout/js/action/create-shipping-address',
    'Magento_Checkout/js/action/create-billing-address',
    'Magento_Checkout/js/model/address-converter',
    'Magento_Checkout/js/model/quote',
    'Rvvup_Payments/js/helper/checkout-data-helper',
    'Rvvup_Payments/js/helper/is-express-payment'
], function (
    wrapper,
    checkoutData,
    selectShippingAddress,
    createShippingAddress,
    createBillingAddress,
    addressConverter,
    quote,
    checkoutDataHelper,
    isExpressPayment
) {
    'use strict';

    return function (checkoutDataResolver) {

        checkoutDataResolver.getShippingAddressFromCustomerAddressList = wrapper.wrapSuper(
            checkoutDataResolver.getShippingAddressFromCustomerAddressList,
            function () {
                if (!isExpressPayment()) {
                    return this._super();
                }
                let shippingAddressFromData = window.checkoutConfig.shippingAddressFromData;
                if (shippingAddressFromData) {
                    checkoutData.setShippingAddressFromData(shippingAddressFromData);
                    checkoutData.setSelectedShippingAddress('new-customer-address');
                    let shippingAddress = addressConverter.formAddressDataToQuoteAddress(
                        checkoutData.getShippingAddressFromData()
                    );
                    checkoutData.setNewCustomerShippingAddress(shippingAddress);
                    createShippingAddress(shippingAddress);
                    selectShippingAddress(shippingAddress);
                    return shippingAddress;
                }
                return this._super();
            });

        checkoutDataResolver.applyBillingAddress = wrapper.wrapSuper(
            checkoutDataResolver.applyBillingAddress,
            function () {
                if (isExpressPayment() && quote.isVirtual()) {
                    let billingAddressFromData = checkoutDataHelper.getRvvupBillingAddress() || window.checkoutConfig.shippingAddressFromData;
                    if (billingAddressFromData) {
                        var newBillingAddress = createBillingAddress(billingAddressFromData);
                        checkoutData.setBillingAddressFromData(newBillingAddress);
                    }
                }

                return this._super();
            });

            checkoutDataResolver.applyBillingAddress = wrapper.wrapSuper(
                checkoutDataResolver.applyBillingAddress,
                function () {
                    // If we are on an express payment not on a virtual product without a billing address then use the
                    // shipping address.
                    if (isExpressPayment() && !quote.isVirtual() && !quote.billingAddress()) {
                        var shippingAddress = quote.shippingAddress();
                        quote.billingAddress(shippingAddress);
                    }

                    this._super();
                }
            )

        return checkoutDataResolver;
    };
});
