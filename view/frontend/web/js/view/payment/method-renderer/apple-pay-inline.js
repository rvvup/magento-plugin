define([
        'Rvvup_Payments/js/view/payment/method-renderer/rvvup-method',
    'ko',
    'jquery',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/action/set-shipping-information',
    'mage/storage',
    'Magento_Checkout/js/model/url-builder',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/error-processor',
    'Rvvup_Payments/js/action/checkout/payment/create-payment-session',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Rvvup_Payments/js/view/payment/methods/place-order-helpers',
    'underscore',

    'domReady!'
    ], function (
        Component,
        ko,
        $,
        loader,
        setShippingInformation,
        storage,
        urlBuilder,
        quote,
        errorProcessor,
        createPaymentSession,
        additionalValidators,
        placeOrderHelpers,
        _,
    ) {
        'use strict';


    let applePayPromise = window.rvvup_sdk.createPaymentMethod("APPLE_PAY", {
        checkoutSessionKey: rvvup_parameters.checkout.token,
        total: getQuoteTotal(),
    }).catch(e => {
        console.error("Error creating Apple Pay payment method", e);
    });

    function getQuoteTotal()
    {
        let quoteTotals = quote.totals();
        let total = {amount: "0", currency: "GBP"};
        if (!quoteTotals) {
            return total;
        }
        if (quoteTotals.grand_total) {
            total.amount = quoteTotals.grand_total.toString();
        }
        if (quoteTotals.quote_currency_code) {
            total.currency = quoteTotals.quote_currency_code;
        }
        return total;

    }

    let $redirectUrl = null;

        return Component.extend({
            defaults: {
                templates: {
                    rvvupPlaceOrderTemplate: 'Rvvup_Payments/payment/method/apple-pay/place-order',
                },
            },
            renderable: ko.observable(false),
            canRender: function () {
                return this.renderable;
            },
            showInfographic: function () {
                return false;
            },

            initialize: function () {
                this._super();
                let self = this;
                applePayPromise.then(async function (applePay) {
                    applePay.on("ready", async () => {
                        self.renderable(await applePay.canMakePayment());
                    });
                });
            },

            mountApplePayButton: function () {
                let self = this;
                applePayPromise.then(async function (applePay) {
                    applePay.on("click", () => {
                        applePay.update({total: getQuoteTotal()})
                    });
                    applePay.on("validate", () => {
                        return placeOrderHelpers.validate(self, additionalValidators);
                    });
                    applePay.on("beforePaymentAuth", async () => {
                        return await self.beforePayment(self)
                    });
                    applePay.on("paymentAuthorized", (data) => {
                        self.paymentAuthorized(data);
                    });
                    applePay.on("paymentFailed", (data) => {
                        errorProcessor.process('Payment ' + data.reason, self.messageContainer)
                    });
                    await applePay.mount({
                        selector: "#rvvup-apple-pay-button",
                    });
                });
            },

            beforePayment: async function (component) {
                try {
                    if(placeOrderHelpers.shouldSaveShippingInformation()){
                        await setShippingInformation();
                    }
                    const response = await createPaymentSession(
                        component.messageContainer,
                        rvvup_parameters.checkout.id,
                        component.getData()
                    );

                    $redirectUrl = response.redirect_url;
                    return {paymentSessionId: response.payment_session_id};
                } catch (e) {
                    errorProcessor.process('Error creating payment, ' + e, component.messageContainer)
                    console.error("Error creating payment", e);
                    loader.stopLoader();
                    return false;
                }
            },

            paymentAuthorized: async function (data) {
                console.log("paymentAuthorized", data);
                window.location.href = $redirectUrl;
            }
        });


    }
);
