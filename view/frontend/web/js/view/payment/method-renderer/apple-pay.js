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
    ) {
        'use strict';

    const applePayPromise = window.rvvup_sdk.createPaymentMethod("APPLE_PAY",
        {
            checkoutSessionKey: rvvup_parameters.checkout.token
        });

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
                    const canMakePayment = await applePay.canMakePayment();
                    if (!canMakePayment) {
                        return;
                    }
                    self.canRender(true);
                });
            },

            mountApplePayButton: function () {
                let self = this;
                applePayPromise.then(async function (applePay) {
                    applePay.mount({
                        selector: "#rvvup-apple-pay-button",
                    });
                    applePay.on("beforePayment", async (data) => {
                        return await self.beforePayment(self, data)
                    });
                    applePay.on("paymentAuthorized", (data) => {
                        self.paymentAuthorized(data);
                    });
                });
            },

            beforePayment: async function (component, data) {
                loader.startLoader();
                try {
                    await setPaymentInformation(component.messageContainer, component.getData(), false);
                    const serviceUrl = urlBuilder.createUrl('/rvvup/payments/:cartId/create-payment-session/:checkoutId', {
                        cartId: quote.getQuoteId(),
                        checkoutId: rvvup_parameters.checkout.id
                    });
                    const response = await $.when(storage.post(
                        serviceUrl,
                        true,
                        'application/json',
                        {}
                    ));
                    loader.stopLoader();
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
