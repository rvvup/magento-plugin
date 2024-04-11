define([
    'mage/utils/wrapper',
    'jquery',
], function (wrapper, $) {
    'use strict';

    return function (placeOrderAction) {
        return wrapper.wrap(placeOrderAction, function (originalAction, paymentData, redirectOnSuccess) {
            if (paymentData && paymentData.method.startsWith('rvvup_')) {
                // do not create order for rvvup payments as it will be created later on.
                return;
            }

            return originalAction(paymentData, redirectOnSuccess);
        });
    };
});