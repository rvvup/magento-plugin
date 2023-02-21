define([
    'ko',
    'domReady!'
], function (ko) {
    'use strict';

    let paymentToken = ko.observable(null),
        redirectUrl = ko.observable(null),
        captureUrl = ko.observable(null),
        cancelUrl = ko.observable(null);

    return {
        paymentToken: paymentToken,
        redirectUrl: redirectUrl,
        captureUrl: captureUrl,
        cancelUrl: cancelUrl,

        /**
         * @return {String|null}
         */
        getPaymentToken: function () {
            return paymentToken();
        },

        /**
         * @param {String|null} value
         */
        setPaymentToken: function (value) {
            paymentToken(value)
        },

        /**
         * @return {String|null}
         */
        getRedirectUrl: function () {
            return redirectUrl();
        },

        /**
         * @param {String|null} value
         */
        setRedirectUrl: function (value) {
            redirectUrl(value)
        },

        /**
         * @return {String|null}
         */
        getCaptureUrl: function () {
            return captureUrl();
        },

        /**
         * @param {String|null} value
         */
        setCaptureUrl: function (value) {
            captureUrl(value)
        },

        /**
         * @return {String|null}
         */
        getCancelUrl: function () {
            return cancelUrl();
        },

        /**
         * @param {String|null} value
         */
        setCancelUrl: function (value) {
            cancelUrl(value)
        },

        /**
         * Reset data to default.
         */
        resetDefaultData: function () {
            this.setPaymentToken(null);
            this.setRedirectUrl(null);
            this.setCaptureUrl(null);
            this.setCancelUrl(null);
        }
    };
});
