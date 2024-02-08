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
                if (key === 'rvvup_APPLE_PAY' && !window.ApplePaySession) {
                    continue;
                }
                rendererList.push({
                    type: key,
                    component: value.component
                });
            }
        }
        return Component.extend({});
    }
);
