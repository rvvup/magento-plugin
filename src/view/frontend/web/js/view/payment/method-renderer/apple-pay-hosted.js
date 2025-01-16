define([
        'Rvvup_Payments/js/view/payment/method-renderer/rvvup-method',

        'domReady!'
    ], function (
        Component,
    ) {
        'use strict';

        return Component.extend({
            canRender: function () {
                return !!window.ApplePaySession;
            },
        });
    }
);
