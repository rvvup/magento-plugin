define([
    '!domReady'
], function () {
    'use strict';

    /**
     * Get PayPal's button styling for Checkout from the window rvvup_parameters object.
     *
     * Fallback to Rvvup's Checkout default, if rvvup_parameters object is not set.
     *
     * @return {Object}
     */
    return function () {
        if (typeof rvvup_parameters !== 'object') {
            return {
                layout: 'vertical',
                color: 'blue',
                shape: 'rect',
                label: 'paypal',
                tagline: false
            }
        }

        const layout = rvvup_parameters?.settings?.paypal?.checkout?.button?.layout?.value || 'vertical';
        const color = rvvup_parameters?.settings?.paypal?.checkout?.button?.color?.value || 'blue';
        const shape = rvvup_parameters?.settings?.paypal?.checkout?.button?.shape?.value || 'rect';
        const label = rvvup_parameters?.settings?.paypal?.checkout?.button?.label?.value || 'paypal';
        const tagline = rvvup_parameters?.settings?.paypal?.checkout?.button?.tagline || false;
        const size = rvvup_parameters?.settings?.paypal?.checkout?.button?.size || null;

        let style = {
            layout: layout,
            color: color,
            shape: shape,
            label: label,
            tagline: tagline,
        };

        if (size !== null) {
            style.height = size;
        }

        return style;
    };
});