define([
    'jquery',
    'underscore',
    'mage/storage',
    'mage/url',
    'mage/cookies'
], function ($, _, storage, url) {
    'use strict';

    /**
     * Create Rvvup express payment order.
     *
     * @param {String} cartId
     * @param {String} method
     * @return {Object}
     */
    return function (cartId, method) {
        const payload = {
            cart_id: cartId,
            method_code: method,
            form_key: $.mage.cookies.get('form_key')
        }

        url.setBaseUrl(window.checkout.baseUrl);

        return storage.post(
            url.build('rvvup/express/create'),
            JSON.stringify(payload),
            false,
            'application/json'
        ).done((response) => {
            return response;
        });
    };
});
