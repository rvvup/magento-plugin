define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
    ], function (
        Component,
        rendererList
    ) {
        'use strict';
        for (const [key, value] of Object.entries(window.checkoutConfig.payment)) {
            if (key.startsWith('rvvup_')) {
                rendererList.push({
                    type: key,
                    component: value.component
                });
            }
        }
        return Component.extend({});
    }
);
