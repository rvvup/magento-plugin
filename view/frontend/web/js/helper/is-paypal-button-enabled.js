define([
    '!domReady'
], function () {
    'use strict';

    /**
     * Is PDP button enabled for PayPal?
     */
    return function (scope) {
        return rvvup_parameters?.settings?.paypal?.[scope]?.button?.enabled || false;
    };
});
