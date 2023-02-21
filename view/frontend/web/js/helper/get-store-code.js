define([
    'Rvvup_Payments/js/model/customer-data/express-payment'
], function (expressPaymentData) {
    'use strict';

    /**
     * Get the current store code from either the section data or the window checkoutConfig object.
     *
     * @return {String}
     */
    return function () {
        let storeCode = expressPaymentData.getStoreCode();

        if (storeCode !== null) {
            return storeCode;
        }

        if (typeof window.checkoutConfig !== 'undefined' && window.checkoutConfig.hasOwnProperty('storeCode')) {
            return window.checkoutConfig.storeCode;
        }

        return '';
    };
});
