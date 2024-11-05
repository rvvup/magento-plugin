define([
        'Rvvup_Payments/js/view/payment/method-renderer/rvvup-method',
        'jquery',
        'Magento_Checkout/js/model/full-screen-loader',
        'Rvvup_Payments/js/action/checkout/payment/get-order-payment-actions',
        'Magento_Checkout/js/action/set-payment-information-extended',
        'Rvvup_Payments/js/model/checkout/payment/order-payment-action',
        'mage/storage',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Checkout/js/model/quote',

        'domReady!'
    ], function (
        Component,
        $,
        loader,
        getOrderPaymentActions,
        setPaymentInformation,
        orderPaymentAction,
        storage,
        customer,
        urlBuilder,
        quote,
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                templates: {
                    rvvupPlaceOrderTemplate: 'Rvvup_Payments/payment/method/apple-pay/place-order',
                },
            },

            renderApplePay: function (target, viewModel) {
                let self = this;
                if (!window.Rvvup) {
                    console.error("Rvvup SDK not loaded");
                    return;
                }
                const rvvup = window.Rvvup({
                    publicKey: "ME01HY0KE0CETAFYVR7GG6CC6YTB",
                });
                rvvup.createPaymentMethod("APPLE_PAY", {
                    checkoutSessionKey: rvvup_parameters.rvvup_checkout_token,
                }).then(function (applePay) {
                    applePay.mount({
                        selector: "#rvvup-apple-pay-button",
                    });
                    let serviceUrl = customer.isLoggedIn() ?
                        urlBuilder.createUrl('/rvvup/payments/mine/:cartId/create-payment-session/:checkoutId', {
                            cartId: quote.getQuoteId(),
                            checkoutId: rvvup_parameters.checkout_id
                        }) :
                        urlBuilder.createUrl('/rvvup/payments/:cartId/create-payment-session/:checkoutId', {
                            cartId: quote.getQuoteId(),
                            checkoutId: rvvup_parameters.checkout_id
                        });

                    let paymentSessionId = null;
                    applePay.on("beforePayment", async (data) => {
                        console.log("onBeforePayment", data);
                        loader.startLoader();
                        // Do validators + save shipping address for firecheckout
                        await setPaymentInformation(self.messageContainer, self.getData(), false);
                        paymentSessionId = await $.when(storage.get(
                            serviceUrl,
                            true,
                            'application/json',
                            {}
                        ));
                        loader.stopLoader();
                        return {
                            paymentSessionId,
                            paymentCaptureType: "AUTOMATIC_PLUGIN",
                        };
                    });

                    applePay.on("paymentAuthorized", async (data) => {
                        console.log("paymentAuthorized", data);
                        window.location.href = '/rvvup/redirect/in?rvvup-order-id=' + data.paymentSessionId;
                    });

                });


            }
        });


    }
);
