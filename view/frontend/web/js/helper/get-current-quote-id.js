define([
    'Rvvup_Payments/js/model/customer-data/express-payment'
], function (expressPaymentData) {
    'use strict';

    /**
     * Get the current store code from either the section data or the window checkoutConfig object.
     *
     * @return {string|null}
     */
    return function () {
        return expressPaymentData.getQuoteId();
    };
});
