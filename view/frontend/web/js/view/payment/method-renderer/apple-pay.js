define([
        'Rvvup_Payments/js/view/payment/method-renderer/rvvup-method',

        'domReady!'
    ], function (
        Component,

    ) {
        'use strict';

        return Component.extend({
            defaults: {
                templates: {
                    rvvupPlaceOrderTemplate: 'Rvvup_Payments/payment/method/apple-pay/place-order',
                },
            },

            renderApplePay: function (target, viewModel) {
                target.innerHTML = 'Apple pay button will load here.'
            }
        });


    }
);