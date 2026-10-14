define([
        'Rvvup_Payments/js/view/payment/method-renderer/rvvup-method',
    'ko',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Rvvup_Payments/js/view/payment/methods/place-order-helpers',
    'Rvvup_Payments/js/model/checkout/payment/card-sdk',

    'domReady!'
    ], function (
        Component,
        ko,
        customer,
        additionalValidators,
        placeOrderHelpers,
        cardSdk
    ) {
        'use strict';

    if (!cardSdk.supported) {
        return Component.extend({
            canRender: function () {
                return false;
            },
        });
    }

        return Component.extend({
            defaults: {
                templates: {
                    rvvupPlaceOrderTemplate: 'Rvvup_Payments/payment/method/card/place-order',
                },
            },

            initialize: function () {
                this._super();
                this.formReady = ko.observable(false);
                this.savePaymentMethod = ko.observable(false);
                return this;
            },

            showSaveCard: function () {
                return customer.isLoggedIn() &&
                    !!(rvvup_parameters.settings &&
                        rvvup_parameters.settings.card &&
                        rvvup_parameters.settings.card.savedCardsEnabled);
            },

            getData: function () {
                return {
                    method: this.getCode(),
                    additional_data: {
                        save_payment_method: this.savePaymentMethod()
                    }
                };
            },

            canRender: function () {
                return cardSdk.available;
            },

            mountCardForm: function () {
                let self = this;
                cardSdk.setActiveComponent(this);
                cardSdk.cardReadyPromise.then(async function (card) {
                    card.update({paymentRequest: {total: cardSdk.getQuoteTotal()}});
                    await card.mount({selector: "#rvvup-card-form-container"});
                    self.formReady(true);
                });
            },

            submitCard: function () {
                if (!this.formReady() || !placeOrderHelpers.validate(this, additionalValidators)) {
                    return;
                }
                this.formReady(false);
                cardSdk.setActiveComponent(this);
                cardSdk.startPayment();
                cardSdk.cardPromise.then(function (card) {
                    card.update({paymentRequest: {total: cardSdk.getQuoteTotal()}});
                    card.submit();
                });
            }
        });


    }
);
