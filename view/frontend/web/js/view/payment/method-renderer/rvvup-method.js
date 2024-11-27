define([
        'Magento_Checkout/js/view/payment/default',
        'jquery',
        'ko',
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
        'Rvvup_Payments/js/method/paypal/cancel',
        'cardPayment',
        'mage/url',
        'Magento_Checkout/js/action/set-payment-information-extended',
        'Magento_Ui/js/model/messageList',
        'Magento_Customer/js/model/customer',
        'Rvvup_Payments/js/view/payment/methods/place-order-helpers',
        'Magento_Checkout/js/action/set-shipping-information',
        'domReady!'
    ], function (
        Component,
        $,
        ko,
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
        cancel,
        cardPayment,
        url,
        setPaymentInformation,
        messageList,
        customer,
        placeOrderHelpers,
        setShippingInformation,
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Rvvup_Payments/payment/rvvup',
                templates: {
                    rvvupCancelExpressPaymentTemplate: 'Rvvup_Payments/payment/cancel-express-payment',
                    rvvupPlaceOrderTemplate: 'Rvvup_Payments/payment/place-order',
                    rvvupCardFormTemplate: 'Rvvup_Payments/payment/card-form',
                    rvvupPaypalButtonTemplate: 'Rvvup_Payments/payment/paypal-button',
                    rvvupPaypalPayLaterTemplate: 'Rvvup_Payments/payment/paypal-pay-later',
                    rvvupIframeModalTemplate: 'Rvvup_Payments/payment/iframe-modal',
                    rvvupIframeSrcTemplate: 'Rvvup_Payments/payment/iframe-src',
                    rvvupPaymentTitleTemplate: 'Rvvup_Payments/payment/payment-title',
                },
                redirectAfterPlaceOrder: false
            },
            getCustomTemplate: function (name) {
                return this.templates[name] || '';
            },


            initialize: function () {
                this._super();
                let self = this;
                self.dynamicIframeUrl = ko.observable(null);

                self.getIframe = ko.computed(() => {
                    return self.dynamicIframeUrl() ||
                        (window.checkoutConfig && window.checkoutConfig.payment[self.index] && window.checkoutConfig.payment[self.index].summary_url);
                });

                /* Set express payment Checkout flag on component initialization */
                rvvupMethodProperties.setIsExpressPaymentCheckout(isExpressPayment());

                quote.paymentMethod.subscribe(function (data) {
                    // If we move away from Paypal method and we already have an order ID then trigger cancel.
                    if (isExpressPayment() && data.method !== 'rvvup_PAYPAL') {
                        this.cancelPayPalPayment();
                    }
                }.bind(this));

                window.addEventListener("message", (event) => {
                    // Prevent listener firing on every component
                    if (this.getCode() !== this.isChecked()) {
                        return;
                    }
                    switch (event.data.type) {
                        case 'rvvup-payment-modal|close':
                            this.triggerModalCancelUrl();

                            break;
                        case 'rvvup-payment-modal|resize':
                            let maxHeight = window.innerHeight - 40;
                            let maxWidth = window.innerWidth - 40;
                            let height = event.data.hasOwnProperty('height') ? event.data.height : null;
                            let width = event.data.hasOwnProperty('width') ? event.data.width : null;
                            let chosenWidth = width > maxWidth ? maxWidth : width;
                            let chosenHeight = height > maxHeight ? maxHeight : height;
                            let finalWidth = width === "max" ? maxWidth : chosenWidth;
                            let finalHeight = height === "max" ? maxHeight : chosenHeight;
                            
                            let items = [
                                document.getElementById(this.getIframeId()), // iframe
                                document.querySelector('.modal-inner-wrap.rvvup'), // modal
                            ];
                            items.forEach(function (item) {
                                if (item) {
                                    item.style.width = finalWidth + 'px';
                                    item.style.height = finalHeight + 'px';
                                }
                            })
                            break;
                        case "rvvup-info-widget|resize":
                            let url = event.data.hasOwnProperty('url') ? event.data.url : '';
                            $('.rvvup-summary[src="' + url + '"]').css({width, height});
                            break;
                        case "rvvup-payment-modal|prevent-close":
                            this.modal._destroyOverlay();
                    }
                }, false);

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
                })
                quote.totals.subscribe(function (newValue) {
                    if (!self.index || !window.checkoutConfig.payment[self.index]) {
                        return;
                    }
                    let url = window.checkoutConfig.payment[self.index].summary_url;
                    if (!url) {
                        return;
                    }
                    // If we have a url template without an amount query, then lets ignore it.
                    if (url.indexOf("amount=") < 0) {
                        return;
                    }
                    let urlWithAmount = url.replace(/amount=(\d+\.\d+)&/, 'amount=' + newValue.base_grand_total + '&');
                    if (self.dynamicIframeUrl === urlWithAmount) {
                        return;
                    }
                    self.dynamicIframeUrl(urlWithAmount);
                });
            },

            cancelPayPalPayment: function () {
                var url = orderPaymentAction.getCancelUrl();
                this.resetDefaultData();
                loader.stopLoader();
                if (url) {
                    this.showModal(url);
                }
            },

            getPaypalBlockStyling: function () {
                return window.checkoutConfig.payment[this.index].style;
            },

            renderCardForm: function () {

                if (rvvup_parameters.settings.card.flow === "INLINE") {
                    $('body').trigger("processStart");
                    window.rvvup_card_rendered = false;
                    this.render(this);
                }
            },

            render: function (context) {
                if (window.rvvup_card_rendered === false) {
                    this.renderCardFields(context);
                }
            },

            renderCardFields: function (context) {
                if (typeof SecureTrading === "function" && window.rvvup_card_rendered === false) {
                    window.SecureTrading = SecureTrading({
                        jwt: rvvup_parameters.settings.card.initializationToken,
                        animatedCard: true,
                        livestatus: rvvup_parameters.settings.card.liveStatus,
                        buttonId: "tp_place_order",
                        deferInit: true,
                        submitOnSuccess: false,
                        panIcon: true,
                        stopSubmitFormOnEnter: true,
                        formId: "st-form",
                        submitCallback: function (data) {
                            var submitData = {
                                auth: data.jwt,
                                form_key: $.mage.cookies.get('form_key')
                            };
                            if (data.threedresponse) {
                                submitData["three_d"] = data.threedresponse;
                            }

                            context.confirmCardAuthorization(submitData, context);
                        },
                        errorCallback: function () {
                            messageList.addErrorMessage({
                                message: $t('Something went wrong')
                            });
                            // This ajax call will reload quote and cancel orders with payment
                            let data = {form_key: $.mage.cookies.get('form_key')};
                            $.ajax({
                                type: "POST",
                                data: data,
                                url: url.build('rvvup/payment/cancel'),
                                complete: function (e) {
                                    $('body').trigger("processStop");
                                },
                            });
                            $('body').trigger("processStop");
                        },
                        translations: {
                            "Card number": rvvup_parameters.settings.card?.form?.translation?.label?.cardNumber || "Card Number",
                            "Expiration date": rvvup_parameters.settings.card?.form?.translation?.label?.expiryDate || "Expiration Date",
                            "Security code": rvvup_parameters.settings.card?.form?.translation?.label?.securityCode || "Security Code",
                            Pay: rvvup_parameters.settings.card?.form?.translation?.button?.pay || "Pay",
                            Processing: rvvup_parameters.settings.card?.form?.translation?.button?.processing || "Processing",
                            "Field is required":
                                rvvup_parameters.settings.card?.form?.translation?.error?.fieldRequired || "Field is required",
                            "Value is too short":
                                rvvup_parameters.settings.card?.form?.translation?.error?.valueTooShort || "Value is too short",
                            "Value mismatch pattern":
                                rvvup_parameters.settings.card?.form?.translation?.error?.valueMismatch || "Value is invalid",
                        },
                        styles: {
                            "background-color-input": "#FFFFFF",
                            "border-color-input": "#EBEBF2",
                            "border-radius-input": "8px",
                            "border-size-input": "1px",
                            "color-input": "#050505",
                            "border-color-input-error": "#ff4545",
                            "color-label": "#050505",
                            "position-left-label": "0.5rem",
                            "font-size-label": "1.2rem",
                            "font-size-message": "1rem",
                            "space-outset-message": "0rem 0px 0px 0.5rem",
                            // a few browser engines round up the iframe context window, making it cut off the border.
                            // Giving it a padding helps to prevent this.
                            "space-inset-body": "0 1px 0 0",
                        },
                    });
                    window.SecureTrading.Components();
                    window.rvvup_card_rendered = true;
                    $('body').trigger("processStop");
                } else {
                    setTimeout(function () {
                        context.render(context);
                    }, 250);
                }
            },

            confirmCardAuthorization: function (submitData, context, remainingRetries = 5) {
                $.ajax({
                    type: "POST",
                    url: url.build('rvvup/cardpayments/confirm'),
                    data: submitData,
                    dataType: "json",
                    success: function (e) {
                        if (e.success) {
                            context.showModal(orderPaymentAction.getCaptureUrl());
                        } else {
                            if (remainingRetries > 0 && e.retryable) {
                                setTimeout(function () {
                                    context.confirmCardAuthorization(submitData, context, remainingRetries - 1);
                                }, 2000);
                                return;
                            }

                            if (e.error_message == null) {
                                messageList.addErrorMessage({
                                    message: $t('Something went wrong')
                                });
                            } else {
                                messageList.addErrorMessage({
                                    message: $t(e.error_message)
                                });
                            }
                            let data = {form_key: $.mage.cookies.get('form_key')};

                            $.ajax({
                                type: "POST",
                                data: data,
                                url: url.build('rvvup/payment/cancel'),
                                complete: function (e) {
                                    $('body').trigger("processStop");
                                },
                            });
                        }
                    },
                });
            },

            getPaypalBlockBorderStyling: function () {
                return window.checkoutConfig.payment[this.index].border;
            },

            getPaypalBlockABackgroundStyling: function () {
                return window.checkoutConfig.payment[this.index].background;
            },

            getUsePlaceOrderStyling: function () {
                return window.checkoutConfig.payment[this.index].use_place_order_styling;
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
                            if(!placeOrderHelpers.validate(self, additionalValidators)) {
                                return reject(createError);
                            }
                            let saveShippingPromise = placeOrderHelpers.shouldSaveShippingInformation() ? setShippingInformation() : $.Deferred().resolve();

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
                return this.isPayPalComponent() && !window.checkoutConfig.payment[this.index].is_express;
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
                let data = {form_key: $.mage.cookies.get('form_key')};
                $.ajax({
                    type: "POST",
                    data: data,
                    url: url.build('rvvup/payment/cancel'),
                });
                window.location.reload();
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

                iframe.src = `${url}&auto_resize=1`;

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
                if (!customer.isLoggedIn()) {
                    if (!quote.guestEmail) {
                        var email = document.getElementById('customer-email');
                        if (email) {
                            quote.guestEmail = email.value;
                        }
                    }
                }
                setPaymentInformation(self.messageContainer, self.getData(), false).done(function () {
                    let code = self.getCode();
                    $.when(getOrderPaymentActions(self.messageContainer)).done(function () {
                        if (code === 'rvvup_CARD' && rvvup_parameters.settings.card.flow === "INLINE") {
                            window.SecureTrading.updateJWT(orderPaymentAction.getPaymentToken());
                            $("#tp_place_order").trigger("click");
                            return;
                        }
                        if (code === 'rvvup_APPLE_PAY' && orderPaymentAction.getRedirectUrl() !== null) {
                            window.location.replace(orderPaymentAction.getRedirectUrl());
                            return;
                        }

                        if (orderPaymentAction.getRedirectUrl() !== null) {
                            self.showRvvupModal(orderPaymentAction.getRedirectUrl());
                        }
                    })
                }).fail(function () {
                    loader.stopLoader();
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
            },
            canRender: function () {
                return true;
            },

            showInfographic: function () {
                return this.getCode() !== 'rvvup_CARD' ||
                    (this.getCode() === 'rvvup_CARD' && rvvup_parameters.settings.card.flow !== 'INLINE');
            }
        });
    }
);
