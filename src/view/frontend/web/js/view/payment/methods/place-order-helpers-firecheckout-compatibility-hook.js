define(['mage/utils/wrapper'], function (wrapper) {
    'use strict';

    return function (target) {
        if (!checkoutConfig || !checkoutConfig.isFirecheckout) {
            return target;
        }

        try {
            const fcModule = {
                validator: require('Swissup_Firecheckout/js/model/validator'),
                layout: require('Swissup_Firecheckout/js/model/layout')
            };

            // Extend validation if firecheckout module is present
            target.validate = wrapper.wrapSuper(
                target.validate,
                function (main, additionalValidators) {
                    this._super(main, additionalValidators);
                    if (fcModule !== null) {
                        return fcModule.validator.validateShippingAddress() && fcModule.validator.validateShippingRadios();
                    }
                    return true;
                }
            );
            target.shouldSaveShippingInformation = wrapper.wrapSuper(
                target.shouldSaveShippingInformation,
                function () {
                    if (fcModule === null) {
                        return false;
                    }
                    return !fcModule.layout.isMultistep();
                }
            )
        } catch (e) {
        }
        return target;
    };
});
