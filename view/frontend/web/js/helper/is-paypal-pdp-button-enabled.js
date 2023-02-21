define([
    '!domReady'
], function (expressPaymentData) {
    'use strict';

    /**
     * Is PDP button enabled for PayPal?
     */
    return function () {
        return rvvup_parameters?.settings?.paypal?.product?.button?.enabled || false;
    };
});
