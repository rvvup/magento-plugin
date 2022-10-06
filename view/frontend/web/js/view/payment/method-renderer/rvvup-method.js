define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/redirect-on-success',
        'mage/url',
        'jquery',
        'Magento_Ui/js/modal/modal',
        'text!Rvvup_Payments/template/modal.html',
        'Magento_Checkout/js/model/totals',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/quote'
    ],
    function (Component, redirectOnSuccessAction, url, $, modal, popupTpl, totals, loader, urlBuilder, storage, customer, quote) {
        'use strict';
        return Component.extend({
            defaults: {
                template: 'Rvvup_Payments/payment/rvvup',
                redirectAfterPlaceOrder: false,
                redirectOutPath: 'rvvup/redirect/out',
                captureUrl: null,
                cancelUrl: null,
            },
            initialize: function () {
                this._super();
                window.addEventListener("message", (event) => {
                    let height = event.data.hasOwnProperty('height') ? event.data.height : null,
                        width = event.data.hasOwnProperty('width') ? event.data.width : null;
                    switch (event.data.type) {
                        case 'rvvup-payment-modal|close':
                            loader.startLoader();
                            window.location.href = '/rvvup/redirect/cancel'
                            break;

                        case 'rvvup-payment-modal|resize':
                            let windowHeight = window.innerHeight,
                                windowWidth = window.innerWidth,
                                finalWidth = width === "max" ? windowWidth - 40 : width > windowWidth ? windowWidth : width,
                                finalHeight = height === "max" ? windowHeight - 40 : height > windowHeight ? windowHeight : height;
                            $('.checkout-index-index .modal-popup .modal-inner-wrap').animate({width: finalWidth + 'px', height: finalHeight + 'px'}, 400);
                            $('.checkout-index-index .modal-popup .modal-inner-wrap #' + this.getIframeId()).css({width: finalWidth + 'px', height: finalHeight + 'px'});
                            break;
                        case "rvvup-info-widget|resize":
                            let url = event.data.hasOwnProperty('url') ? event.data.url : '';
                            $('.rvvup-summary[src="' + url + '"]').css({ width, height });
                            break;
                    }
                }, false);
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
                    createOrder: function(data, actions) {
                        loader.startLoader();

                        return self.getRedirectOutData().then((responseData) => {
                            self.captureUrl = responseData.paymentActions.capture.redirect_url;
                            self.cancelUrl = responseData.paymentActions.cancel.redirect_url;

                            return responseData.paymentActions.authorization.token;
                        });
                    },
                    onApprove: function () {
                        return new Promise((resolve, reject) => {
                            resolve(self.captureUrl);
                        }).then((url) => {
                            loader.stopLoader();
                            self.showModal(url);
                        });
                    },
                    onCancel: function () {
                        loader.stopLoader();

                        self.showModal(self.cancelUrl);
                    },
                    onError: function (error) {
                        console.error(error);
                        loader.stopLoader();
                        alert('Unable to place order!');
                    },
                }).render('#' + this.getPayPalId());
            },
            getPayPalId: function () {
                if (this.isPayPalComponent()) {
                    return 'paypalPlaceholder';
                }
            },
            isPayPalComponent: function() {
                return this.index === 'rvvup_PAYPAL';
            },
            afterPlaceOrder: function () {
                var serviceUrl
                if (customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl('/rvvup/redirect', {});
                } else {
                    serviceUrl = urlBuilder.createUrl('/rvvup/redirect/:cartId', {
                        cartId: quote.getQuoteId()
                    });
                }
                let self = this;
                storage.post(
                    serviceUrl,
                    {},
                    true,
                    'application/json'
                ).done(function (result) {
                    self.showRvvupModal(result);
                })
            },
            getIframe: function () {
                let grandTotal = parseFloat(totals.totals()['grand_total']);
                let url = window.checkoutConfig.payment[this.index].summary_url
                return url.replace(/amount=(\d+\.\d+)&/, 'amount=' + grandTotal + '&')
            },
            getLogoUrl: function () {
                return window.checkoutConfig.payment[this.index].logo;
            },
            getDescription: function () {
                return window.checkoutConfig.payment[this.index].description;
            },
            showRvvupModal: function (url) {
                // Don't ask 😭
                this.showModal(url)
            },
            showModal: function (url) {
                if (!this.modal) {
                    var options = {
                        type: 'popup',
                        responsive: true,
                        clickableOverlay: false,
                        innerScroll: true,
                        modalClass: 'rvvup',
                        buttons: [],
                        popupTpl: popupTpl
                    };
                    this.modal = modal(options, $('#' + this.getModalId()))
                }

                let iframe = document.getElementById(this.getIframeId())

                if (iframe === null) {
                    return false;
                }

                iframe.src = url;

                this.modal.openModal();
            },
            getModalId: function () {
                return 'rvvup_modal-' + this.getCode()
            },
            getIframeId: function () {
                return 'rvvup_iframe-' + this.getCode()
            },
            getRedirectOutData: function() {
                return fetch(url.build(this.redirectOutPath))
                    .then((response) => response.json())
                    .then((data) => { return data; });
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
