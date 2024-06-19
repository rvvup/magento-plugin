define([
    'ko',
    'domReady!'
], function (ko) {
    'use strict';

    let placedOrderId = ko.observable(null),
        isCancellationTriggered = ko.observable(false),
        isExpressPaymentCheckout = ko.observable(false);

    return {
        placedOrderId: placedOrderId,
        isCancellationTriggered: isCancellationTriggered,
        isExpressPaymentCheckout: isExpressPaymentCheckout,

        /**
         * @return {*}
         */
        getPlacedOrderId: function () {
            return placedOrderId();
        },

        /**
         * @param {*} value
         */
        setPlacedOrderId: function (value) {
            placedOrderId(value)
        },

        /**
         * @return {Boolean}
         */
        getIsCancellationTriggered: function () {
            return isCancellationTriggered();
        },

        /**
         * @param {Boolean} value
         */
        setIsCancellationTriggered: function (value) {
            isCancellationTriggered(value)
        },

        /**
         * @return {Boolean}
         */
        getIsExpressPaymentCheckout: function () {
            return isExpressPaymentCheckout();
        },

        /**
         * @param {Boolean} value
         */
        setIsExpressPaymentCheckout: function (value) {
            isExpressPaymentCheckout(value)
        },

        /**
         * Reset data to default.
         */
        resetDefaultData: function () {
            this.setPlacedOrderId(null);
            this.setIsCancellationTriggered(false);
            this.setIsExpressPaymentCheckout(false);
        }
    };
});
