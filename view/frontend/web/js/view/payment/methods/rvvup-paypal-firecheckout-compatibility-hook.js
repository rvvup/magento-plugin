define(['mage/utils/wrapper', 'jquery'], function (wrapper, $) {
    'use strict';

    return function (target) {
        if (!checkoutConfig || !checkoutConfig.isFirecheckout) {
            return target;
        }

        try {
            console.log($(
                [
                    '.actions-toolbar:not([style="display: none;"])',
                    '.action.checkout:not([style="display: none;"])'
                ].join(' '),
                '.payment-method._active'
            ));
            $(
                [
                    '.actions-toolbar:not([style="display: none;"])',
                    '.action.checkout:not([style="display: none;"])'
                ].join(' '),
                '.payment-method._active'
            ).hide();
            console.log($(
                [
                    '.actions-toolbar:not([style="display: none;"])',
                    '.action.checkout:not([style="display: none;"])'
                ].join(' '),
                '.payment-method._active'
            ));
            const fcModule = {
                placeOrderModel: require('Swissup_Firecheckout/js/model/place-order')
            };

            target.beforeSetPaymentInformation = wrapper.wrapSuper(
                target.beforeSetPaymentInformation,
                function () {
                    if (fcModule !== null) {
                        return fcModule.placeOrderModel.placeOrder();
                    }
                    return true;
                }
            );
        } catch (e) {
        }
        return target;
    };
});
