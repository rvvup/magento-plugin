define(['mage/utils/wrapper'], function (wrapper) {
    'use strict';

    return function (target) {
        if (!checkoutConfig || !checkoutConfig.isFirecheckout) {
            return target;
        }
        const fireCheckoutModule = function () {
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
        target.validate = wrapper.wrapSuper(
            target.validate,
            function (main, additionalValidators) {
                this._super(main, additionalValidators);
                const fcModule = fireCheckoutModule();
                if (fcModule !== null) {
                    return fireCheckoutValidation(fcModule)
                }
                return true;
            }
        );

        return target;
    };
});
