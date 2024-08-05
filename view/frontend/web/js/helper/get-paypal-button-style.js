define([
    '!domReady'
], function () {
    'use strict';

    /**
     * Get PayPal's button styling for PDP from the window rvvup_parameters object.
     *
     * Fallback to Rvvup's PDP default, if rvvup_parameters object is not set.
     *
     * @return {Object}
     */
    return function (scope) {
        if (typeof rvvup_parameters !== 'object') {
            return {
                layout: 'vertical',
                color: 'gold',
                shape: 'rect',
                label: 'paypal',
                tagline: false,
            };
        }

        const layout = rvvup_parameters?.settings?.paypal?.[scope]?.button?.layout?.value || 'vertical';
        const color = rvvup_parameters?.settings?.paypal?.[scope]?.button?.color?.value || 'gold';
        const shape = rvvup_parameters?.settings?.paypal?.[scope]?.button?.shape?.value || 'rect';
        const label = rvvup_parameters?.settings?.paypal?.[scope]?.button?.label?.value || 'paypal';
        const tagline = rvvup_parameters?.settings?.paypal?.[scope]?.button?.tagline || false;
        const size = rvvup_parameters?.settings?.paypal?.[scope]?.button?.size || null;

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
