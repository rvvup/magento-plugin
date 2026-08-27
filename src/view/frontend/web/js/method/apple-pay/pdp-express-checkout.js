define([
    'uiComponent',
    'jquery',
    'mage/translate',
    'Rvvup_Payments/js/action/add-to-cart',
    'Rvvup_Payments/js/action/create-cart',
    'Rvvup_Payments/js/action/create-checkout-on-demand',
    'Rvvup_Payments/js/action/empty-cart',
    'Rvvup_Payments/js/action/set-session-message',
    'Rvvup_Payments/js/helper/get-add-to-cart-payload',
    'Rvvup_Payments/js/helper/get-current-quote-id',
    'Rvvup_Payments/js/helper/get-pdp-form',
    'Rvvup_Payments/js/helper/get-store-code',
    'Rvvup_Payments/js/helper/is-logged-in',
    'Rvvup_Payments/js/helper/validate-pdp-form',
    'mage/storage',
    'Magento_Checkout/js/model/url-builder',
    'domReady!'
], function (
    Component,
    $,
    $t,
    addToCart,
    createCart,
    createCheckoutOnDemand,
    emptyCart,
    setSessionMessage,
    getAddToCartPayload,
    getCurrentQuoteId,
    getPdpForm,
    getStoreCode,
    isLoggedIn,
    validatePdpForm,
    storage,
    checkoutUrlBuilder
) {
    'use strict';

    return Component.extend({
        defaults: {
            containerId: null,
            initialPrice: null,
            currency: 'GBP',
            expressCheckoutInstance: null,
            cartId: null
        },

        /**
         * Initialize the Apple Pay express checkout on PDP.
         *
         * @param {Object} config
         */
        initialize: function (config) {
            this.containerId = config.containerId;
            this.initialPrice = config.initialPrice;
            this.currency = config.currency || 'GBP';

            if (!this.containerId || !this.initialPrice) {
                return this;
            }

            if (!window.rvvup_sdk) {
                console.error('Rvvup SDK not loaded');
                return this;
            }

            this.render();
            return this;
        },

        /**
         * Create and mount the express checkout Apple Pay button.
         */
        render: function () {
            var self = this;
            var containerEl = document.getElementById(self.containerId);

            if (!containerEl) {
                return;
            }

            var form = getPdpForm(containerEl);

            window.rvvup_sdk.createExpressCheckout({
                enabledPaymentMethods: ['APPLE_PAY'],
                paymentRequest: {
                    total: {
                        amount: self.initialPrice.toString(),
                        currency: self.currency
                    }
                }
            }).then(function (expressCheckout) {
                self.expressCheckoutInstance = expressCheckout;

                expressCheckout.on('ready', function () {
                    expressCheckout.mount({selector: '#' + self.containerId});
                });

                expressCheckout.on('beforePaymentAuth', function () {
                    return self.handleBeforePaymentAuth(form);
                });

                expressCheckout.on('paymentAuthorized', function () {
                    self.handlePaymentCompleted();
                });

                expressCheckout.on('paymentCaptured', function () {
                    self.handlePaymentCompleted();
                });

                expressCheckout.on('paymentFailed', function (data) {
                    $('body').trigger('processStop');
                    setSessionMessage($t('Payment failed: ' + (data.reason || 'Unknown error')), 'error');
                });

            }).catch(function (error) {
                console.error('Error creating Apple Pay express checkout:', error);
            });

            // Listen for price updates (configurable products, qty changes, etc.)
            $(document).on('priceUpdated', '.product-info-main .price-box', function (event, data) {
                var amount = self.getFinalAmount(data);
                if (amount == null || !self.expressCheckoutInstance) {
                    return;
                }
                self.expressCheckoutInstance.update({
                    paymentRequest: {
                        total: {
                            currency: self.currency,
                            amount: amount.toString()
                        }
                    }
                });
            });
        },

        /**
         * Handle the beforePaymentAuth event:
         * 1. Validate the PDP form
         * 2. Create or empty cart
         * 3. Add product to cart
         * 4. Create Rvvup checkout on demand
         * 5. Create payment session
         * 6. Return { checkoutSessionKey, paymentSessionId }
         *
         * @param {Element|null} form
         * @return {Promise}
         */
        handleBeforePaymentAuth: function (form) {
            var self = this;

            // Validate the PDP form (checks required options, qty, etc.)
            if (form && !validatePdpForm(form)) {
                return Promise.resolve(false);
            }

            $('body').trigger('processStart');

            return self.prepareCart()
                .then(function (cartId) {
                    self.cartId = cartId;

                    if (!form) {
                        return $.Deferred().reject('Product form not found').promise();
                    }

                    // Add product to the cart
                    return addToCart(cartId, getAddToCartPayload(form, cartId));
                })
                .then(function () {
                    // Create Rvvup checkout on demand
                    return createCheckoutOnDemand();
                })
                .then(function (checkoutData) {
                    // Create payment session using the checkout
                    return self.createPaymentSession(self.cartId, checkoutData.id)
                        .then(function (sessionData) {
                            $('body').trigger('processStop');
                            return {
                                checkoutSessionKey: checkoutData.token,
                                paymentSessionId: sessionData.payment_session_id
                            };
                        });
                })
                .catch(function (error) {
                    $('body').trigger('processStop');
                    console.error('Error in beforePaymentAuth:', error);
                    setSessionMessage($t('Something went wrong while processing your payment'), 'error');
                    return false;
                });
        },

        /**
         * Create or empty the cart, returning a cart ID.
         *
         * @return {Object} jQuery Deferred resolving to cartId
         */
        prepareCart: function () {
            var currentQuoteId = getCurrentQuoteId();

            if (currentQuoteId === null) {
                return createCart();
            }

            return emptyCart(currentQuoteId);
        },

        /**
         * Create a payment session for the given cart and checkout.
         *
         * Uses the existing webapi endpoint: /V1/rvvup/payments/:cartId/create-payment-session/:checkoutId
         *
         * @param {string} cartId
         * @param {string} checkoutId
         * @return {Object} jQuery Deferred resolving to { payment_session_id, redirect_url }
         */
        createPaymentSession: function (cartId, checkoutId) {
            checkoutUrlBuilder.storeCode = getStoreCode();

            var serviceUrl;
            var payload = {
                cartId: cartId,
                paymentMethod: {
                    method: 'rvvup_apple_pay'
                },
                billingAddress: {}
            };

            if (!isLoggedIn()) {
                serviceUrl = checkoutUrlBuilder.createUrl(
                    '/rvvup/payments/:cartId/create-payment-session/:checkoutId',
                    {cartId: cartId, checkoutId: checkoutId}
                );
                payload.email = 'pending@applepay.express';
            } else {
                serviceUrl = checkoutUrlBuilder.createUrl(
                    '/rvvup/payments/mine/:cartId/create-payment-session/:checkoutId',
                    {cartId: cartId, checkoutId: checkoutId}
                );
            }

            return storage.post(
                serviceUrl,
                JSON.stringify(payload),
                true,
                'application/json',
                {}
            );
        },

        /**
         * Handle successful payment (authorized or captured) - redirect to success page.
         */
        handlePaymentCompleted: function () {
            $('body').trigger('processStart');
            window.location.href = window.checkout
                ? window.checkout.baseUrl + 'checkout/onepage/success/'
                : '/checkout/onepage/success/';
        },

        /**
         * Extract the final amount from a priceUpdated event data payload.
         *
         * @param {Object} data
         * @return {number|null}
         */
        getFinalAmount: function (data) {
            return (data && data.finalPrice && data.finalPrice.amount)
                || (data && data.prices && data.prices.finalPrice && data.prices.finalPrice.amount)
                || null;
        }
    });
});
