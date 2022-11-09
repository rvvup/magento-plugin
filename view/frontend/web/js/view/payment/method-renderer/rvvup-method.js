define([
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'Magento_Ui/js/modal/modal',
        'text!Rvvup_Payments/template/modal.html',
        'Magento_Checkout/js/model/totals',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/error-processor',
        'underscore'
    ], function (
        Component,
        $,
        modal,
        popupTpl,
        totals,
        loader,
        urlBuilder,
        storage,
        customer,
        additionalValidators,
        quote,
        errorProcessor,
        _
    ) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Rvvup_Payments/payment/rvvup',
                redirectAfterPlaceOrder: false,
                placedOrderId: null,
                paymentToken: null,
                redirectUrl: null,
                captureUrl: null,
                cancelUrl: null,
                cancelledUrlTriggered: false,
            },
            initialize: function () {
                this._super();
                window.addEventListener("message", (event) => {
                    // Prevent listener firing on every component
                    if (this.getCode() !== this.isChecked()) {
                        return;
                    }
                    let height = event.data.hasOwnProperty('height') ? event.data.height : null,
                        width = event.data.hasOwnProperty('width') ? event.data.width : null;
                    switch (event.data.type) {
                        case 'rvvup-payment-modal|close':
                            this.triggerModalCancelUrl();

                            break;
                        case 'rvvup-payment-modal|resize':
                            let windowHeight = window.innerHeight,
                                windowWidth = window.innerWidth,
                                chosenWidth = width > windowWidth ? windowWidth : width,
                                chosenHeight = height > windowHeight ? windowHeight : height,
                                finalWidth = width === "max" ? windowWidth - 40 : chosenWidth,
                                finalHeight = height === "max" ? windowHeight - 40 : chosenHeight;
                            let items = [];
                            items.push(document.querySelector('.modal-inner-wrap.rvvup'));
                            items.push(document.getElementById(this.getIframeId()));
                            items.forEach(function (item) {
                                item.animate([{
                                    width: finalWidth + 'px',
                                    height: finalHeight + 'px'
                                }], {
                                    duration: 400,
                                    fill: 'forwards'
                                });
                            })
                            break;
                        case "rvvup-info-widget|resize":
                            let url = event.data.hasOwnProperty('url') ? event.data.url : '';
                            $('.rvvup-summary[src="' + url + '"]').css({ width, height });
                            break;
                        case "rvvup-payment-modal|prevent-close":
                            this.modal._destroyOverlay();
                    }
                }, false);

                /**
                 * Add event listener on AJAX success event of order placement which returns the order ID.
                 * Set placedOrderId attribute to use if required from the component. We expect an integer.
                 * Set the value only if the payment was done via a Rvvup payment component.
                 */
                $(document).ajaxSuccess(function(event, xhr, settings) {
                    if (settings.type !== 'POST' ||
                        xhr.statusCode !== 200 ||
                        !settings.url.includes('/payment-information') ||
                        !xhr.hasOwnProperty('responseJSON')
                    ) {
                        return;
                    }

                    /* Check we are in component's property exist */
                    if (!this.hasOwnProperty('placedOrderId') || typeof placedOrderId === 'undefined') {
                        return;
                    }

                    /* if response is a positive integer, set it as the order ID. */
                    this.placedOrderId = /^\d+$/.test(xhr.responseJSON) ? xhr.responseJSON : null;
                });
            },
            renderPayPalButton: function () {
                let self = this;

                if (!document.getElementById(this.getPayPalId())) {
                    console.error(this.getPayPalId() + ' not found in DOM');
                    return;
                }

                if (!window.paypal) {
                    console.error('PayPal SDK not loaded');
                    return;
                }

                if (document.getElementById(this.getPayPalId()).childElementCount > 0) {
                    console.log('button already rendered');
                    return;
                }

                paypal.Buttons({
                    style: {
                        layout: 'vertical',
                        color:  'blue',
                        shape:  'rect',
                        label:  'paypal'
                    },
                    /**
                     * On PayPal button click replicate core Magento JS Place Order functionality.
                     * Use async validation as per PayPal button docs
                     * If placedOrderId is set, resolve & continue.
                     *
                     * @see https://developer.paypal.com/docs/checkout/standard/customize/validate-user-input/
                     *
                     * @param data
                     * @param actions
                     * @returns {Promise<never>|*}
                     */
                    onClick: function(data, actions) {
                        if (self.validate() &&
                            additionalValidators.validate() &&
                            self.isPlaceOrderActionAllowed() === true
                        ) {
                            self.isPlaceOrderActionAllowed(false);
                            return self.getPlaceOrderDeferredObject()
                                .done(function () {
                                    if (self.placedOrderId !== null) {
                                        return actions.resolve();
                                    }

                                    return actions.reject();
                                }).fail(function() {
                                    return actions.reject();
                                }).always(function () {
                                    self.isPlaceOrderActionAllowed(true);
                                });
                        } else {
                            return actions.reject();
                        }
                    },
                    /**
                     * On create Order, get the token from the order payment actions.
                     *
                     * @returns {Promise<unknown>}
                     */
                    createOrder: function() {
                        loader.startLoader();
                        return new Promise((resolve, reject) => {
                            return $.when(self.getOrderPaymentActions())
                                .done(function() {
                                    return resolve();
                                }).fail(function() {
                                    return reject();
                                });
                        }).then(() => {
                            return self.paymentToken;
                        });
                    },
                    /**
                     * On PayPal approved, show modal with capture URL.
                     *
                     * @returns {Promise<unknown>}
                     */
                    onApprove: function () {
                        return new Promise((resolve, reject) => {
                            resolve(self.captureUrl);
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
                            resolve(self.cancelUrl);
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
                        console.error(error);
                        self.resetDefaultData();
                        loader.stopLoader();
                        errorProcessor.process('Unable to place order!', self.messageContainer)
                    },
                }).render('#' + this.getPayPalId());
            },
            getPayPalId: function () {
                if (this.isPayPalComponent()) {
                    return 'paypalPlaceholder';
                }
            },
            /**
             * Validate if this is the PayPal component.
             *
             * @returns {boolean}
             */
            isPayPalComponent: function() {
                return this.index === 'rvvup_PAYPAL';
            },
            /**
             * After Place order actions.
             * If PayPal payment, allow paypal buttons to handle logic.
             */
            afterPlaceOrder: function () {
                let self = this;
                loader.startLoader();

                if (self.isPayPalComponent()) {
                    return;
                }

                $.when(self.getOrderPaymentActions())
                    .done(function() {
                        if (self.redirectUrl !== null) {
                            self.showRvvupModal(self.redirectUrl);
                        }
                    });
            },
            getIframe: function () {
                let grandTotal = parseFloat(totals.getSegment('grand_total').value);
                let url = window.checkoutConfig.payment[this.index].summary_url;
                return url.replace(/amount=(\d+\.\d+)&/, 'amount=' + grandTotal + '&')
            },
            getLogoUrl: function () {
                return window.checkoutConfig.payment[this.index].logo;
            },
            getDescription: function () {
                return window.checkoutConfig.payment[this.index].description;
            },
            showRvvupModal: function (url) {
                /* Seems redundant but Modal was not called after successful payment otherwise */
                this.showModal(url)
            },
            /**
             * Handle event when user clicks outside the modal.
             *
             * @param event
             * @returns {Promise<unknown>}
             */
            outerClickHandler: function (event) {
                this.triggerModalCancelUrl()
            },
            /**
             * Handle setting cancel URL in the modal, prevents multiple clicks.
             */
            triggerModalCancelUrl: function () {
                if (!this.cancelUrl || this.cancelledUrlTriggered === true) {
                    return;
                }

                this.cancelledUrlTriggered = true;

                this.setIframeUrl(this.cancelUrl);
            },
            showModal: function (url) {
                if (!this.modal) {
                    let options = {
                        type: 'popup',
                        outerClickHandler: this.outerClickHandler.bind(this),
                        innerScroll: true,
                        modalClass: 'rvvup',
                        buttons: [],
                        popupTpl: popupTpl
                    };
                    this.modal = modal(options, $('#' + this.getModalId()))
                }
                loader.stopLoader();

                if (!this.setIframeUrl(url)) {
                    return false;
                }

                this.modal.openModal();
            },
            /**
             * Set the iFrame's URL.
             *
             * @param url
             */
            setIframeUrl: function (url) {
                let iframe = document.getElementById(this.getIframeId())

                if (iframe === null) {
                    return false;
                }

                iframe.src = url;

                return true;
            },
            getModalId: function () {
                return 'rvvup_modal-' + this.getCode()
            },
            getIframeId: function () {
                return 'rvvup_iframe-' + this.getCode()
            },
            /**
             * Reset Rvvup data to default
             */
            resetDefaultData: function () {
                this.placedOrderId = null;
                this.paymentToken = null;
                this.redirectUrl = null;
                this.captureUrl = null;
                this.cancelUrl = null;
                this.cancelledUrlTriggered = false;
            },
            /**
             * API request to get Order Payment Actions for Rvvup Payments.
             */
            getOrderPaymentActions: function () {
                let self = this,
                    serviceUrl = customer.isLoggedIn() ?
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
                    let paymentAction = _.find(data, function(action) {return action.type === 'authorization'});

                    if (typeof paymentAction === 'undefined') {
                        errorProcessor.process('There was an error when placing the order!', self.messageContainer)

                        return;
                    }

                    /* Set cancelUrl from cancelAction method */
                    let cancelAction = _.find(data, function(action) {return action.type === 'cancel'});

                    self.cancelUrl = typeof cancelAction !== 'undefined' && cancelAction.method === 'redirect_url'
                        ? cancelAction.value
                        : null;

                    /*
                     * If we have a token authorization type method, then we should have a capture action.
                     */
                    if (paymentAction.method === 'token') {
                        let captureAction = _.find(data, function(action) {return action.type === 'capture'});

                        self.captureUrl = typeof captureAction !== 'undefined' && captureAction.method === 'redirect_url'
                            ? captureAction.value
                            : null;

                        self.paymentToken = paymentAction.value;

                        return self.paymentToken;
                    }

                    /* Otherwise, this should be standard redirect authorization, so show the modal */
                    if (paymentAction.method === 'redirect_url') {
                        self.redirectUrl = paymentAction.value;

                        return self.redirectUrl;
                    }

                    throw 'Error placing order';
                }).fail(function(response) {
                    errorProcessor.process(response, self.messageContainer);
                });
            },
            /**
             * After Render for the paypal component's placeholder.
             * It handles auto-loading the button after knockout.js has finished all processes.
             */
            afterRenderPaypalComponentProcessor: function(target, viewModel) {
                if (this.isPayPalComponent()) {
                    this.renderPayPalButton();
                }
            }
        });
    }
);
