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
    'mage/translate',

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
        $t,
    ) {
        'use strict';

    // If we don't have a checkout token, issue with loading checkout, then don't load apple pay
    if (!rvvup_parameters.checkout || !rvvup_parameters.checkout.token) {
        console.error("Apple Pay not loaded as checkout token is missing.");
        return Component.extend({
            canRender: function () {
                return false;
            },
        });
    }


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

    /**
     * The SDK accumulates event listeners and fires "ready" only once, while Magento can
     * re-create the renderer whenever the payment list re-renders. Handlers are therefore
     * registered exactly once here and delegate to the active component instance.
     */
    let activeComponent = null;
    let renderable = ko.observable(false);

    applePayPromise.then(function (applePay) {
        if (!applePay) {
            return;
        }
        applePay.on("ready", async () => {
            renderable(await applePay.canMakePayment());
        });
        applePay.on("click", () => {
            applePay.update({total: getQuoteTotal()})
        });
        applePay.on("validate", () => {
            return placeOrderHelpers.validate(activeComponent, additionalValidators);
        });
        applePay.on("beforePaymentAuth", async () => {
            return await activeComponent.beforePayment(activeComponent)
        });
        applePay.on("paymentAuthorized", (data) => {
            activeComponent.paymentAuthorized(data);
        });
        applePay.on("paymentFailed", (data) => {
            errorProcessor.process({
                    responseText:
                        JSON.stringify({message: $t('Payment %1').replace('%1', data.reason)})
                },
                activeComponent.messageContainer);
        });
    });

        return Component.extend({
            defaults: {
                templates: {
                    rvvupPlaceOrderTemplate: 'Rvvup_Payments/payment/method/apple-pay/place-order',
                },
            },
            renderable: renderable,
            canRender: function () {
                return this.renderable;
            },
            showInfographic: function () {
                return false;
            },

            mountApplePayButton: function () {
                activeComponent = this;
                applePayPromise.then(async function (applePay) {
                    if (!applePay) {
                        return;
                    }
                    await applePay.mount({
                        selector: "#rvvup-apple-pay-button",
                    });
                });
            },

            beforePayment: async function (component) {
                try {
                    if (placeOrderHelpers.shouldSaveShippingInformation()) {
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
                    errorProcessor.process({
                            responseText:
                                JSON.stringify({message: $t('Error creating payment, %1').replace('%1', e)})
                        },
                        component.messageContainer);
                    console.error("Error creating payment", e);
                    loader.stopLoader();
                    return false;
                }
            },

            paymentAuthorized: function () {
                loader.startLoader();
                window.location.href = $redirectUrl;
            }
        });


    }
);
