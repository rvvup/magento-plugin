define([
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'mage/translate',
        'Magento_Ui/js/modal/modal',
        'text!Rvvup_Payments/template/modal.html',
        'Magento_Checkout/js/model/totals',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/quote',
        'Rvvup_Payments/js/action/checkout/payment/get-order-payment-actions',
        'Rvvup_Payments/js/action/checkout/payment/remove-express-payment',
        'Rvvup_Payments/js/helper/get-paypal-checkout-button-style',
        'Rvvup_Payments/js/helper/is-express-payment',
        'Rvvup_Payments/js/model/checkout/payment/order-payment-action',
        'Rvvup_Payments/js/model/checkout/payment/rvvup-method-properties',
        'Rvvup_Payments/js/method/paypal/cancel'
    ], function (
        Component,
        $,
        $t,
        modal,
        popupTpl,
        totals,
        loader,
        additionalValidators,
        errorProcessor,
        quote,
        getOrderPaymentActions,
        removeExpressPayment,
        getPayPalCheckoutButtonStyle,
        isExpressPayment,
        orderPaymentAction,
        rvvupMethodProperties,
        cancel
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Rvvup_Payments/payment/rvvup',
                redirectAfterPlaceOrder: false
            },
            initialize: function () {
                this._super();
                /* Set express payment Checkout flag on component initialization */
                rvvupMethodProperties.setIsExpressPaymentCheckout(isExpressPayment());

                quote.paymentMethod.subscribe(function (data) {
                    // If we move away from Paypal method and we already have an order ID then trigger cancel.
                    if (data.method !== 'rvvup_PAYPAL' && rvvupMethodProperties.getPlacedOrderId() !== null) {
                        this.cancelPayPalPayment();
                    }

                    // Make sure Data Method is paypal before we setup the event listener.
                    if (data.method === 'rvvup_PAYPAL') {
                        document.addEventListener('click', this.checkDomElement.bind(this));
                    }
                }.bind(this));

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
                $(document).ajaxSuccess(function (event, xhr, settings) {
                    if (settings.type !== 'POST' ||
                        xhr.status !== 200 ||
                        !settings.url.includes('/payment-information') ||
                        !xhr.hasOwnProperty('responseJSON')
                    ) {
                        return;
                    }

                    /* Check we are in current component, by our model is defined */
                    if (typeof rvvupMethodProperties === 'undefined') {
                        return;
                    }

                    /* if response is a positive integer, set it as the order ID. */
                    rvvupMethodProperties.setPlacedOrderId(/^\d+$/.test(xhr.responseJSON) ? xhr.responseJSON : null);
                });
                /* Cancel Express Payment on click event. */
                $(document).on('click', 'a#' + this.getCancelExpressPaymentLinkId(), (e) => {
                    e.preventDefault();

                    if (!rvvupMethodProperties.getIsExpressPaymentCheckout()) {
                        return;
                    }

                    loader.startLoader();
                    $.when(removeExpressPayment())
                        .done(() => {
                            window.location.reload();
                        });
                })
            },

            checkDomElement: function(event) {
                // Setup elements we want to make sure we cancel on.
                const elements = document.querySelectorAll('button.action, span[id="block-discount-heading"], span[id="block-giftcard-heading"], .opc-progress-bar-item, input[id="billing-address-same-as-shipping-rvvup_PAYPAL"]');
                // Only check if we have a placeOrderID this shows if we have clicked on the cards
                if (rvvupMethodProperties.getPlacedOrderId() !== null) {
                    // If we are not in the boundary and have clicked on the elements above cancel payment.
                    if(Array.from(elements).some(element => element.contains(event.target))) {
                        this.cancelPayPalPayment();
                        document.removeEventListener("click", this.checkDomElement);
                    }
                }
            },

            cancelPayPalPayment: function () {
                var url = orderPaymentAction.getCancelUrl();
                this.resetDefaultData();
                loader.stopLoader();
                this.showModal(url);
            },

            getPaypalBlockStyling: function () {
                return window.checkoutConfig.payment[this.index].style;
            },

            getPaypalBlockBorderStyling: function () {
                return window.checkoutConfig.payment[this.index].border;
            },

            getPaypalBlockABackgroundStyling: function () {
                return window.checkoutConfig.payment[this.index].background;
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

                rvvup_paypal.Buttons({
                    style: getPayPalCheckoutButtonStyle(),
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
                    onClick: function (data, actions) {
                        if (self.validate() &&
                            additionalValidators.validate() &&
                            self.isPlaceOrderActionAllowed() === true
                        ) {
                            self.isPlaceOrderActionAllowed(false);
                            return self.getPlaceOrderDeferredObject()
                                .done(function () {
                                    if (rvvupMethodProperties.getPlacedOrderId() !== null) {
                                        return actions.resolve();
                                    }

                                    return actions.reject();
                                }).fail(function () {
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
                    createOrder: function () {
                        loader.startLoader();
                        return new Promise((resolve, reject) => {
                            return $.when(getOrderPaymentActions(self.messageContainer))
                                .done(function () {
                                    return resolve();
                                }).fail(function () {
                                    return reject();
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
                        console.error(error);
                        self.resetDefaultData();
                        loader.stopLoader();
                        errorProcessor.process('Unable to place order!', self.messageContainer)
                    },
                }).render('#' + this.getPayPalId());
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
             * Check whether we should display the PayPal Button.
             *
             * @return {boolean}
             */
            shouldDisplayPayPalButton() {
                return this.isPayPalComponent() && !rvvupMethodProperties.getIsExpressPaymentCheckout();
            },

            /**
             * Check whether we should display the cancel Express Payment Link. Currently limited to PayPal.
             *
             * @return {false}
             */
            shouldDisplayCancelExpressPaymentLink() {
                return rvvupMethodProperties.getIsExpressPaymentCheckout();
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
                $(document).on('click', '#' + this.getCancelExpressPaymentLinkId(), function(){
                    cancel.cancelPayment();
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

            /**
             * Get the component's iframe with the related payment method summary_url.
             *
             * @return {string}
             */
            getIframe: function () {
                let grandTotal = parseFloat(totals.getSegment('grand_total').value);
                let url = window.checkoutConfig.payment[this.index].summary_url;
                return url.replace(/amount=(\d+\.\d+)&/, 'amount=' + grandTotal + '&')
            },

            /**
             * Get the component's logo URL.
             *
             * @return {string}
             */
            getLogoUrl: function () {
                return window.checkoutConfig.payment[this.index].logo;
            },

            /**
             * Get the component's description.
             *
             * @return {string}
             */
            getDescription: function () {
                return window.checkoutConfig.payment[this.index].description;
            },

            /**
             * Show the Rvvup's modal with the injected specified URL for the iframe's src attribute.
             *
             * This method seems redundant but Modal was not called after successful payment otherwise
             * @param {string} url
             */
            showRvvupModal: function (url) {
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
                if (!orderPaymentAction.getCancelUrl()
                    || rvvupMethodProperties.getIsCancellationTriggered() === true
                    || this.preventClose) {
                    return;
                }

                rvvupMethodProperties.setIsCancellationTriggered(true);

                this.setIframeUrl(orderPaymentAction.getCancelUrl());
            },

            /**
             * Show the modal injecting the specified URL in the iframe's src attribute.
             * @param {string} url
             * @return {Boolean|void}
             */
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
             * Set the component's iFrame element src attribute URL.
             *
             * @param {string} url
             */
            setIframeUrl: function (url) {
                let iframe = document.getElementById(this.getIframeId())

                if (iframe === null) {
                    return false;
                }

                iframe.src = url;

                return true;
            },

            /**
             * Get the component's modal element ID.
             *
             * @return {string}
             */
            getModalId: function () {
                return 'rvvup_modal-' + this.getCode()
            },

            /**
             * Get the component's iframe element ID.
             *
             * @return {string}
             */
            getIframeId: function () {
                return 'rvvup_iframe-' + this.getCode()
            },

            /**
             * Reset Rvvup data to default
             */
            resetDefaultData: function () {
                rvvupMethodProperties.resetDefaultData();
                orderPaymentAction.resetDefaultData();
            },

            /**
             * After Place order actions.
             * If PayPal payment, allow paypal buttons to handle logic.
             */
            afterPlaceOrder: function () {
                let self = this;
                loader.startLoader();

                if (self.shouldDisplayPayPalButton()) {
                    return;
                }

                $.when(getOrderPaymentActions(self.messageContainer))
                    .done(function () {
                        if (orderPaymentAction.getRedirectUrl() !== null) {
                            self.showRvvupModal(orderPaymentAction.getRedirectUrl());
                        }
                    });
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
            }
        });
    }
);
