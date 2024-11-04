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
        'Rvvup_Payments/js/helper/is-express-payment',
        'Rvvup_Payments/js/model/checkout/payment/order-payment-action',
        'Rvvup_Payments/js/model/checkout/payment/rvvup-method-properties',
        'cardPayment',
        'mage/url',
        'Magento_Checkout/js/action/set-payment-information-extended',
        'Magento_Ui/js/model/messageList',
        'Magento_Customer/js/model/customer',
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
        isExpressPayment,
        orderPaymentAction,
        rvvupMethodProperties,
        cardPayment,
        url,
        setPaymentInformation,
        messageList,
        customer,
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Rvvup_Payments/payment/rvvup',
                templates: {
                    rvvupPlaceOrderTemplate: 'Rvvup_Payments/payment/place-order',
                    rvvupCardFormTemplate: 'Rvvup_Payments/payment/card-form',
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
                                /** Remove 80pixels as margin from top */
                                finalHeight = height === "max" ? windowHeight - 80 - 40 : chosenHeight;
                            let items = [];
                            items.push(document.getElementById(this.getIframeId()));
                            items.push(document.querySelector('.modal-inner-wrap.rvvup'));
                            items.forEach(function (item) {
                                if (item) {
                                    item.animate([{
                                        width: finalWidth + 'px',
                                        height: finalHeight + 'px'
                                    }], {
                                        duration: 400,
                                        fill: 'forwards'
                                    });
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

            getUsePlaceOrderStyling: function () {
                return window.checkoutConfig.payment[this.index].use_place_order_styling;
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

            preventAfterPlaceOrder: function () {
                return false;
            },

            /**
             * After Place order actions.
             */
            afterPlaceOrder: function () {
                let self = this;
                loader.startLoader();

                if (self.preventAfterPlaceOrder()) {
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
        });
    }
);
