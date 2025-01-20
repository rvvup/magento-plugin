define([
    'mage/storage',
    'Magento_Checkout/js/model/url-builder',
    'Rvvup_Payments/js/helper/get-store-code',
    'Rvvup_Payments/js/helper/is-logged-in'
], function (storage, checkoutUrlBuilder, getStoreCode, isLoggedIn) {
    'use strict';

    /**
     * Empty existing quote.
     *
     * Implement done only, allow failures to be captured by calling function.
     * API Call response is the quoteId (or Masked Quote ID for guest).
     *
     * @return {string}
     */
    return function (cartId) {
        /* Set the store code for the checkout URL Builder */
        checkoutUrlBuilder.storeCode = getStoreCode();

        let serviceUrl = isLoggedIn() ?
            checkoutUrlBuilder.createUrl('/rvvup/payments/carts/mine', {}):
            checkoutUrlBuilder.createUrl('/rvvup/payments/guest-carts/:cartId', {
                cartId: cartId
            });

        return storage.delete(
            serviceUrl,
            true,
            'application/json'
        ).done((cartId) => {
            return cartId;
        });
    };
});
