define([
    'mage/storage',
    'Magento_Checkout/js/model/url-builder',
    'Rvvup_Payments/js/helper/get-store-code',
    'Rvvup_Payments/js/helper/is-logged-in'
], function (storage, checkoutUrlBuilder, getStoreCode, isLoggedIn) {
    'use strict';

    /**
     * Set billing address to the quote.
     *
     * @param {String} cartId
     * @param {Object} payload
     * @return {*}
     */
    return function (cartId, payload) {
        /* Set the store code for the checkout URL Builder */
        checkoutUrlBuilder.storeCode = getStoreCode();

        let serviceUrl = isLoggedIn() ?
            checkoutUrlBuilder.createUrl('/carts/mine/billing-address', {}):
            checkoutUrlBuilder.createUrl('/guest-carts/:cartId/billing-address', {
                cartId: cartId
            });

        return storage.post(
            serviceUrl,
            JSON.stringify(payload),
            true,
            'application/json'
        ).done((response) => {
            return response;
        });
    };
});
