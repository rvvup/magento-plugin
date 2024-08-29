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
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_ReCaptchaWebapiUi/js/webapiReCaptchaRegistry'
    ], function (
        $,
        _,
        storage,
        customer,
        errorProcessor,
        quote,
        urlBuilder,
        orderPaymentAction,
        rvvupMethodProperties,
        loader,
        recaptchaRegistry
    ) {
        'use strict';

    const getOrderPaymentActions = function (serviceUrl, headers, messageContainer) {
        return storage.get(
            serviceUrl,
            true,
            'application/json',
            headers
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
            loader.stopLoader();
            errorProcessor.process(response, messageContainer);
        });
    }

    const removeReCaptchaListener = function (reCaptchaId) {
        // Old version of Magento Security Package does not have _isInvisibleType property
        if (!recaptchaRegistry._isInvisibleType) {
            return;
        }
        // Do not remove it for invisible reCaptcha
        if (recaptchaRegistry._isInvisibleType.hasOwnProperty('recaptcha-checkout-place-order') &&
            recaptchaRegistry._isInvisibleType['recaptcha-checkout-place-order'] === true
        ) {
            return;
        }
        recaptchaRegistry.removeListener(reCaptchaId)
    }
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

            const reCaptchaId = 'recaptcha-checkout-place-order';
            // ReCaptcha is enabled for placing orders, so trigger the recaptcha flow
            if (recaptchaRegistry.triggers && recaptchaRegistry.triggers.hasOwnProperty(reCaptchaId)) {
                var recaptchaDeferred = $.Deferred();
                recaptchaRegistry.addListener(reCaptchaId, function (token) {
                    //Add reCaptcha value to rvvup place-order request
                    getOrderPaymentActions(serviceUrl, {'X-ReCaptcha': token}, messageContainer)
                        .done(function () {
                            recaptchaDeferred.resolve.apply(recaptchaDeferred, arguments);
                        }).fail(function () {
                        recaptchaDeferred.reject.apply(recaptchaDeferred, arguments);
                    });
                });

                recaptchaRegistry.triggers[reCaptchaId]();

                // Remove Recaptcha to prevent non-place order actions from triggering the listener
                removeReCaptchaListener(reCaptchaId);

                return recaptchaDeferred;
            }

            return getOrderPaymentActions(serviceUrl, {}, messageContainer);
        };
    }
);
