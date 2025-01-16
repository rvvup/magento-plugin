define([
    'mage/storage',
    'Magento_Checkout/js/model/url-builder',
    'Rvvup_Payments/js/helper/get-store-code',
    'Rvvup_Payments/js/helper/is-logged-in',
], function (storage, checkoutUrlBuilder, getStoreCode, isLoggedIn) {
    'use strict';

    /**
     * Add product to cart.
     *
     * @param {String} cartId
     * @param {Object} payload
     * @return {*}
     */
    return function (cartId, payload) {
        /* Set the store code for the checkout URL Builder */
        checkoutUrlBuilder.storeCode = getStoreCode();

        let serviceUrl = isLoggedIn() ?
            checkoutUrlBuilder.createUrl('/carts/mine/items', {}):
            checkoutUrlBuilder.createUrl('/guest-carts/:cartId/items', {
                cartId: cartId
            });

        return storage.post(
            serviceUrl,
            JSON.stringify(payload),
            false,
            'application/json'
        ).done((response) => {
            return response;
        });
    };
});
