define([], function () {
    'use strict';

    return function () {
        return {
            validate: function (main, additionalValidators) {
                if (!main.validate()) {
                    return false;
                }
                if (!additionalValidators.validate()) {
                    return false
                }

                return main.isPlaceOrderActionAllowed() !== false;
            }
        }
    }();
});
