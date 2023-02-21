define([
        'jquery',
        'underscore',
        'mage/storage',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'Rvvup_Payments/js/model/checkout/payment/order-payment-action',
        'Rvvup_Payments/js/model/checkout/payment/rvvup-method-properties',
    ], function (
        $,
        _,
        storage,
        customer,
        errorProcessor,
        quote,
        urlBuilder,
        orderPaymentAction,
        rvvupMethodProperties
    ) {
        'use strict';

        /**
         * API request to get Order Payment Actions for Rvvup Payments.
         */
        return function (messageContainer) {
            let serviceUrl = customer.isLoggedIn() ?
                urlBuilder.createUrl('/rvvup/payments/mine/:cartId/payment-actions', {
                    cartId: quote.getQuoteId()
                }) :
                urlBuilder.createUrl('/rvvup/payments/:cartId/payment-actions', {
                    cartId: quote.getQuoteId()
                });

            return storage.get(
                serviceUrl,
                true,
                'application/json'
            ).done(function (data) {
                /* First check get the authorization action & throw error if we don't. */
                const paymentAction = _.find(data, function (action) {
                    return action.type === 'authorization'
                });

                if (typeof paymentAction === 'undefined') {
                    errorProcessor.process('There was an error when placing the order!', messageContainer)

                    return;
                }

                /* Set cancelUrl from cancelAction method */
                const cancelAction = _.find(data, function (action) {
                    return action.type === 'cancel'
                });

                orderPaymentAction.setCancelUrl(
                    typeof cancelAction !== 'undefined' && cancelAction.method === 'redirect_url'
                        ? cancelAction.value
                        : null
                );

                /*
                 * If we have a token authorization type method, then we should have a capture action.
                 * Return either the payment token or the capture URL for Express Payment Session
                 */
                if (paymentAction.method === 'token') {
                    const captureAction = _.find(data, function (action) {
                        return action.type === 'capture'
                    });

                    orderPaymentAction.setCaptureUrl(
                        typeof captureAction !== 'undefined' && captureAction.method === 'redirect_url'
                            ? captureAction.value
                            : null
                    );

                    orderPaymentAction.setPaymentToken(paymentAction.value);

                    /* If it is an express payment complete, set the capture URL as the redirect URL. */
                    if (rvvupMethodProperties.getIsExpressPaymentCheckout()) {
                        orderPaymentAction.setRedirectUrl(orderPaymentAction.getCaptureUrl());

                        return orderPaymentAction.getRedirectUrl();
                    }

                    return orderPaymentAction.getPaymentToken();
                }

                /* Otherwise, this should be standard redirect authorization. */
                if (paymentAction.method === 'redirect_url') {
                    orderPaymentAction.setRedirectUrl(paymentAction.value);

                    return orderPaymentAction.getRedirectUrl();
                }

                throw 'Error placing order';
            }).fail(function (response) {
                errorProcessor.process(response, messageContainer);
            });
        };
    }
);
