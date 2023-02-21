define([
    'Rvvup_Payments/js/model/customer-data/express-payment'
], function (expressPaymentData) {
    'use strict';

    return function () {
        return expressPaymentData.isExpressPayment();
    };
});
