define([
    'mage/storage',
    'Magento_Checkout/js/model/url-builder',
    'Rvvup_Payments/js/helper/get-store-code',
    'Rvvup_Payments/js/helper/is-logged-in'
], function (storage, checkoutUrlBuilder, getStoreCode, isLoggedIn) {
    'use strict';

    /**
     * Remove express payment data.
     *
     * @param {string} cartId
     * @param {Object} payload
     * @return {*}
     */
    return function (cartId) {
        /* Set the store code for the checkout URL Builder */
        checkoutUrlBuilder.storeCode = getStoreCode();

        let serviceUrl = isLoggedIn() ?
            checkoutUrlBuilder.createUrl('/rvvup/payments/carts/mine/express', {}):
            checkoutUrlBuilder.createUrl('/rvvup/payments/guest-carts/:cartId/express', {
                cartId: cartId
            });

        return storage.delete(
            serviceUrl,
            true,
            'application/json'
        ).done((response) => {
            return response;
        });
    };
});
