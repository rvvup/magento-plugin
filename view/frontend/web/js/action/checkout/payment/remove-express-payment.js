define([
    'mage/storage',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/url-builder'
], function (storage, customer, quote, urlBuilder) {
    'use strict';

    /**
     * Remove express payment data.
     *
     * @param {String} cartId
     * @param {Object} payload
     * @return {*}
     */
    return function () {
        let serviceUrl = customer.isLoggedIn() ?
            urlBuilder.createUrl('/rvvup/payments/carts/mine/express', {}):
            urlBuilder.createUrl('/rvvup/payments/guest-carts/:cartId/express', {
                cartId: quote.getQuoteId()
            });

        return storage.delete(
            serviceUrl,
            true,
            'application/json'
        ).done((response) => {
            window.isRvvupExpressPaypal = false;
            return response;
        });
    };
});
