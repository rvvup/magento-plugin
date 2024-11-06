define([
        'jquery',
        'underscore',
        'mage/storage',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Checkout/js/model/full-screen-loader',
    ], function (
        $,
        _,
        storage,
        customer,
        errorProcessor,
        quote,
        urlBuilder,
        fullScreenLoader,
    ) {
        'use strict';

        var filterTemplateData = function (data) {
            return _.each(data, function (value, key, list) {
                if (_.isArray(value) || _.isObject(value)) {
                    list[key] = filterTemplateData(value);
                }

                if (key === '__disableTmpl' || key === 'title') {
                    delete list[key];
                }
            });
        };

        return function (messageContainer, checkoutId, paymentData) {
            fullScreenLoader.startLoader();
            paymentData = filterTemplateData(paymentData);
            var payload = {
                cartId: quote.getQuoteId(),
                paymentMethod: paymentData,
                billingAddress: quote.billingAddress(),
            };
            var serviceUrl;
            if (!customer.isLoggedIn()) {
                serviceUrl = urlBuilder.createUrl('/rvvup/payments/:cartId/create-payment-session/:checkoutId', {
                    cartId: quote.getQuoteId(),
                    checkoutId: checkoutId
                });
                payload.email = quote.guestEmail;
            } else {
                serviceUrl = urlBuilder.createUrl('/rvvup/payments/mine/:cartId/create-payment-session/:checkoutId', {
                    cartId: quote.getQuoteId(),
                    checkoutId: checkoutId
                });
            }

            return storage.post(
                serviceUrl, JSON.stringify(payload), true, 'application/json', {}
            ).fail(
                function (response) {
                    errorProcessor.process(response, messageContainer);
                }
            ).always(
                function () {
                    fullScreenLoader.stopLoader();
                }
            );
        };
    }
);
