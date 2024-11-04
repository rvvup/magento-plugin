define([
        'Rvvup_Payments/js/view/payment/method-renderer/rvvup-method',
        'jquery',
        'Magento_Checkout/js/model/full-screen-loader',
        'Rvvup_Payments/js/helper/get-paypal-checkout-button-style',
        'Rvvup_Payments/js/view/payment/methods/rvvup-paypal',
        'Magento_Checkout/js/action/set-shipping-information',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/set-payment-information-extended',
        'Rvvup_Payments/js/action/checkout/payment/get-order-payment-actions',
        'Rvvup_Payments/js/model/checkout/payment/order-payment-action',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/totals',
        'mage/translate',
        'Magento_Checkout/js/model/quote',
        'Rvvup_Payments/js/helper/is-express-payment',
        'Rvvup_Payments/js/action/checkout/payment/remove-express-payment',
    'Rvvup_Payments/js/method/paypal/cancel',

        'domReady!'
    ], function (
        Component,
        $,
        loader,
        getPayPalCheckoutButtonStyle,
        rvvupPaypal,
        setShippingInformation,
        additionalValidators,
        setPaymentInformation,
        getOrderPaymentActions,
        orderPaymentAction,
        errorProcessor,
        totals,
        $t,
        quote,
        isExpressPayment,
        removeExpressPayment,
        cancelPaypal,

    ) {
        'use strict';

        return Component.extend({
            defaults: {
                templates: {
                    rvvupPlaceOrderTemplate: 'Rvvup_Payments/payment/method/paypal/place-order',
                },
            },
            initialize: function () {
                this._super();
                let self = this;
                /* Cancel Express Payment on click event. */
                $(document).on('click', 'a#' + this.getCancelExpressPaymentLinkId(), (e) => {
                    e.preventDefault();

                    if (!window.checkoutConfig.payment[this.index].is_express) {
                        return;
                    }

                    loader.startLoader();
                    $.when(removeExpressPayment())
                        .done(() => {
                            window.location.reload();
                        });
                });

                quote.paymentMethod.subscribe(function (data) {
                    // If we move away from Paypal method and we already have an order ID then trigger cancel.
                    if (isExpressPayment() && data.method !== 'rvvup_PAYPAL') {
                        this.cancelPayPalPayment();
                    }
                }.bind(this));

            },

            /**
             * After Render for the PayPal component's placeholder.
             * It handles autoloading of the button after knockout.js has finished all processes.
             */
            afterRenderPaypalComponentProcessor: function (target, viewModel) {
                if (this.shouldDisplayPayPalButton()) {
                    this.renderPayPalButton();
                }
            },

            /**
             * Check whether we should display the PayPal Button.
             *
             * @return {boolean}
             */
            shouldDisplayPayPalButton() {
                return this.isPayPalComponent() && !window.checkoutConfig.payment[this.index].is_express;
            },

            /**
             * Render the PayPal button if the PayPal container is in place.
             */
            renderPayPalButton: function () {
                let self = this;

                if (!this.getPayPalId()) {
                    return;
                }

                if (!document.getElementById(this.getPayPalId())) {
                    console.error(this.getPayPalId() + ' not found in DOM');
                    return;
                }

                if (!window.rvvup_paypal) {
                    console.error('PayPal SDK not loaded');
                    return;
                }

                if (document.getElementById(this.getPayPalId()).childElementCount > 0) {
                    console.log('button already rendered');
                    return;
                }
                const createError = "Something went wrong, please try again later.";

                rvvup_paypal.Buttons({
                    style: getPayPalCheckoutButtonStyle(),
                    /**
                     * On create Order, get the token from the order payment actions.
                     *
                     * @returns {Promise<unknown>}
                     */
                    createOrder: function () {
                        loader.startLoader();
                        return new Promise((resolve, reject) => {
                            if (!rvvupPaypal.validate(self, additionalValidators)) {
                                return reject(createError);
                            }
                            let saveShippingPromise = rvvupPaypal.shouldSaveShippingInformation() ? setShippingInformation() : $.Deferred().resolve();

                            saveShippingPromise
                                .then(function () {
                                    return setPaymentInformation(self.messageContainer, self.getData(), false);
                                })
                                .then(function () {
                                    return $.when(getOrderPaymentActions(self.messageContainer))
                                })
                                .then(function () {
                                    resolve();
                                })
                                .fail(function () {
                                    loader.stopLoader();
                                    return reject(createError);
                                });
                        }).then(() => {
                            loader.stopLoader();
                            return orderPaymentAction.getPaymentToken();
                        });
                    },

                    /**
                     * On PayPal approved, show modal with capture URL.
                     *
                     * @returns {Promise<unknown>}
                     */
                    onApprove: function () {
                        return new Promise((resolve, reject) => {
                            resolve(orderPaymentAction.getCaptureUrl());
                        }).then((url) => {
                            self.resetDefaultData();
                            loader.stopLoader();
                            self.showModal(url);
                        });
                    },
                    /**
                     * On PayPal cancelled, show modal with cancel URL.
                     *
                     * @returns {Promise<unknown>}
                     */
                    onCancel: function () {
                        return new Promise((resolve, reject) => {
                            resolve(orderPaymentAction.getCancelUrl());
                        }).then((url) => {
                            self.resetDefaultData();
                            loader.stopLoader();
                            self.showModal(url);
                        });
                    },
                    /**
                     * On error, display error message in the container.
                     *
                     * @param error
                     */
                    onError: function (error) {
                        self.resetDefaultData();
                        loader.stopLoader();
                        if (!error || error === createError) {
                            return;
                        }
                        errorProcessor.process({
                                responseText:
                                    JSON.stringify({message: error.message || error})
                            },
                            self.messageContainer)
                    },
                }).render('#' + this.getPayPalId());
            },

            getPaypalBlockBorderStyling: function () {
                return window.checkoutConfig.payment[this.index].border;
            },

            getPaypalBlockABackgroundStyling: function () {
                return window.checkoutConfig.payment[this.index].background;
            },
            /**
             * Get the paypal component's paypal button ID.
             *
             * @return {string}
             */
            getPayPalId: function () {
                if (this.isPayPalComponent()) {
                    return 'paypalPlaceholder';
                }
            },

            /**
             * Check whether we should display the cancel Express Payment Link. Currently limited to PayPal.
             *
             * @return {false}
             */
            shouldDisplayCancelExpressPaymentLink() {
                return window.checkoutConfig.payment[this.index].is_express;
            },

            /**
             * Get the express payment cancellation link.
             *
             * @return {string}
             */
            getCancelExpressPaymentLink() {
                let cancelLink = '<a id="' + this.getCancelExpressPaymentLinkId() + '"' +
                    ' href="#payment">' + $t('here') + '</a>';
                return $t('You are currently paying with %1. If you want to cancel this process, please click %2')
                    .replace('%1', this.getTitle())
                    .replace('%2', cancelLink);
            },

            getCancelButtonOnClick() {
                $(document).on('click', '#' + this.getCancelExpressPaymentLinkId(), function () {
                    cancelPaypal.cancelPayment();
                });
            },

            /**
             * Get the ID for the express payment cancellation link
             *
             * @return {string}
             */
            getCancelExpressPaymentLinkId() {
                return 'cancel-express-payment-link-' + this.getCode();
            },

            /**
             * Validate if this is the PayPal component.
             *
             * @returns {Boolean}
             */
            isPayPalComponent: function () {
                return this.index === 'rvvup_PAYPAL';
            },


            getPayLaterTotal: function () {
                return totals.totals().grand_total
            },
            getPayLaterConfigValue: function (key) {
                let values = rvvup_parameters

                const paypalLoaded = !!values?.settings?.paypal?.checkout;

                if (!paypalLoaded) {
                    return false;
                }

                if (['enabled', 'textSize'].includes(key)) {
                    return values.settings.paypal.checkout.payLaterMessaging[key]
                }
                return values.settings.paypal.checkout.payLaterMessaging[key].value
            },

            getPaypalBlockStyling: function () {
                return window.checkoutConfig.payment[this.index].style;
            },

            cancelPayPalPayment: function () {
                var url = orderPaymentAction.getCancelUrl();
                this.resetDefaultData();
                loader.stopLoader();
                if (url) {
                    this.showModal(url);
                }
            },
            preventAfterPlaceOrder: function () {
                return this.shouldDisplayPayPalButton();
            },

        });


    }
);
