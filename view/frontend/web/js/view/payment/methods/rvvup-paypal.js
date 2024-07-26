define([], function () {
    'use strict';

    return function () {
        const fireCheckoutModule = function () {
            if (!checkoutConfig || !checkoutConfig.isFirecheckout) {
                return null;
            }
            try {
                return {
                    validator: require('Swissup_Firecheckout/js/model/validator'),
                    layout: require('Swissup_Firecheckout/js/model/layout')
                };
            } catch (e) {
                return null;
            }
        };
        const fireCheckoutValidation = function (fireCheckoutModule) {
            if (!fireCheckoutModule.validator.validateShippingAddress()) {
                return false;
            }

            return fireCheckoutModule.validator.validateShippingRadios();
        };
        return {
            validate: function (main, additionalValidators) {
                if (!main.validate()) {
                    return false;
                }
                if (!additionalValidators.validate()) {
                    return false
                }

                if (main.isPlaceOrderActionAllowed() === false) {
                    return false;
                }
                //Swiss FireCheckout compatibility
                const fcModule = fireCheckoutModule();
                if (fcModule !== null) {
                    return fireCheckoutValidation(fcModule)
                }
                return true;

            }
        }
    }();
});
