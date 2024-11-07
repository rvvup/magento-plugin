define([
        'Rvvup_Payments/js/view/payment/method-renderer/rvvup-method',
    'ko',
    'jquery',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/action/set-payment-information-extended',
    'mage/storage',
    'Magento_Checkout/js/model/url-builder',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/error-processor',
    'Rvvup_Payments/js/action/checkout/payment/create-payment-session',
    'underscore',

    'domReady!'
    ], function (
        Component,
        ko,
        $,
        loader,
        setPaymentInformation,
        storage,
        urlBuilder,
        quote,
        errorProcessor,
        createPaymentSession,
        _,
    ) {
        'use strict';


    let applePayPromise = window.rvvup_sdk.createPaymentMethod("APPLE_PAY", {
        checkoutSessionKey: rvvup_parameters.checkout.token,
        amount: getQuoteTotal(),
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
            canRender: ko.observable(false),

            initialize: function () {
                this._super();
                let self = this;
                applePayPromise.then(async function (applePay) {
                    self.canRender(applePay.canMakePayment());
                });
            },

            mountApplePayButton: function () {
                let self = this;
                applePayPromise.then(async function (applePay) {
                    applePay.on("click", () => {
                        applePay.update({amount: getQuoteTotal()})
                    });
                    applePay.on("beforePaymentAuth", (data) => {
                        return self.beforePayment(self, data)
                    });
                    applePay.on("paymentAuthorized", (data) => {
                        self.paymentAuthorized(data);
                    });
                    await applePay.mount({
                        selector: "#rvvup-apple-pay-button",
                    });
                });
            },

            beforePayment: async function (component) {
                try {
                    const response = await createPaymentSession(
                        component.messageContainer,
                        rvvup_parameters.checkout.id,
                        component.getData()
                    );

                    $redirectUrl = response.redirect_url;
                    return {
                        paymentSessionId: response.payment_session_id,
                        paymentCaptureType: "AUTOMATIC_PLUGIN", //TODO: get from above request
                    };
                } catch (e) {
                    errorProcessor.process('Error creating payment, '.e, component.messageContainer)
                    console.error("Error creating payment", e);
                    loader.stopLoader();
                    throw e;
                }
            },

            paymentAuthorized: async function (data) {
                console.log("paymentAuthorized", data);
                window.location.href = $redirectUrl;
            }
        });


    }
);
