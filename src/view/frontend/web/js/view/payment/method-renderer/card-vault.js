define([
    'Magento_Vault/js/view/payment/method-renderer/vault',
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

    const CARD_METHOD = 'rvvup_CARD';

    if (!cardSdk.supported) {
        return Component.extend({
            canRender: function () {
                return false;
            },
        });
    }

    /**
     * A saved card is paid through the same SDK card instance as a new card: the server creates the
     * payment session for the saved token, the SDK then runs 3DS and authorises the payment.
     */
    return Component.extend({
        defaults: {
            template: 'Rvvup_Payments/payment/method/card/vault'
        },

        initialize: function () {
            this._super();
            this.formReady = ko.observable(false);
            cardSdk.cardReadyPromise.then(() => this.formReady(true));
            return this;
        },

        /**
         * UI gate only: the server checks that the token belongs to the customer.
         */
        canRender: function () {
            return cardSdk.available() &&
                customer.isLoggedIn() &&
                !!(rvvup_parameters.settings &&
                    rvvup_parameters.settings.card &&
                    rvvup_parameters.settings.card.flow === 'INLINE' &&
                    rvvup_parameters.settings.card.savedCardsEnabled);
        },

        getToken: function () {
            return this.publicHash;
        },

        getMaskedCard: function () {
            return this.details.maskedCC;
        },

        getExpirationDate: function () {
            return this.details.expirationDate;
        },

        getCardType: function () {
            return this.details.type;
        },

        /**
         * The plain card method is submitted, not the vault one, so the session is built like any
         * other card session. The public hash tells the server which saved card to use.
         */
        getData: function () {
            return {
                method: CARD_METHOD,
                additional_data: {
                    public_hash: this.getToken()
                }
            };
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
                card.submit({savedToken: true});
            });
        }
    });
});
