define([
    'mage/storage',
    'Magento_Checkout/js/model/url-builder',
    'Rvvup_Payments/js/helper/get-store-code',
    'Rvvup_Payments/js/helper/is-logged-in'
], function (storage, checkoutUrlBuilder, getStoreCode, isLoggedIn) {
    'use strict';

    /**
     * Create a new empty quote.
     *
     * Implement done only, allow failures to be captured by calling function.
     * API Call response is the quoteId (or Masked Quote ID for guest).
     *
     * @return {*}
     */
    return function () {
        /* Set the store code for the checkout URL Builder */
        checkoutUrlBuilder.storeCode = getStoreCode();

        let serviceUrl = isLoggedIn() ?
            checkoutUrlBuilder.createUrl('/carts/mine', {}) :
            checkoutUrlBuilder.createUrl('/guest-carts', {});

        return storage.post(
            serviceUrl
        ).done((response) => {
            return response;
        });
    };
});
