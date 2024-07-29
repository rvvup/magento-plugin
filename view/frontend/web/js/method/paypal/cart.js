define(
    [
        'uiComponent',
        'jquery',
        'underscore',
        'mage/translate',
        'Rvvup_Payments/js/action/create-express-payment',
        'Rvvup_Payments/js/action/remove-express-payment',
        'Rvvup_Payments/js/action/set-cart-billing-address',
        'Rvvup_Payments/js/action/set-cart-shipping-address',
        'Rvvup_Payments/js/action/set-session-message',
        'Rvvup_Payments/js/helper/get-paypal-button-style',
        'Rvvup_Payments/js/helper/is-paypal-button-enabled',
        'Rvvup_Payments/js/method/paypal/cancel',
        'Magento_Customer/js/customer-data',
        'domReady!'
    ],
    function (
        Component,
        $,
        _,
        $t,
        createExpressPayment,
        removeExpressPayment,
        setCartBillingAddress,
        setCartShippingAddress,
        setSessionMessage,
        getPayPalButtonStyle,
        isPayPalButtonEnabled,
        cancel,
        customerData
    ) {
        'use strict';
        return Component.extend({
            defaults: {
                cartId: null,
                buttonQuerySelector: null,
                scope: null
            },

            /**
             * Component initialization function.
             *
             * We expect the buttonId to be provided when component is initialized, if none, no action.
             * Also, no action if Paypal button is disabled on pdp from Rvvup.
             */
            initialize: function (config) {

                if (this.buttonQuerySelector === null
                    || !isPayPalButtonEnabled(config.scope)) {
                    return this;
                }
                var cartData = customerData.get('cart');
                var self = this;
                cartData.subscribe(function (data) {
                    var buttonElement = document.querySelector(config.buttonQuerySelector);
                    if (buttonElement.childElementCount > 0) {
                        buttonElement.removeChild(buttonElement.getElementsByTagName('div')[0]);
                    }
                    if (data.subtotalAmount > 0) {
                        self.renderPayPalButton(
                            config.buttonQuerySelector,
                            config.cartId,
                            config.scope
                        );
                    }
                });
                if (cartData._latestValue.subtotalAmount > 0) {
                    this.renderPayPalButton(
                        config.buttonQuerySelector,
                        config.cartId,
                        config.scope
                    );
                }
                return this;
            },

            /**
             * Instantiate PayPal Button on the related container.
             *
             * If button element does not exist or already has children elements (PayPal already loaded), no action.
             *
             * @param buttonId
             * @param cartId
             */
            renderPayPalButton: function (buttonId, cartId, scope) {
                let self = this,
                    buttonElement = document.querySelector(buttonId);

                if (!buttonElement) {
                    console.error(buttonId + ' not found in DOM');
                    return;
                }

                if (buttonElement.childElementCount > 0) {
                    console.log('button already rendered');
                    return;
                }

                if (!window.rvvup_paypal) {
                    console.error('PayPal SDK not loaded');
                    return;
                }

                rvvup_paypal.Buttons({
                    style: getPayPalButtonStyle(scope),
                    onClick: function (data, actions) {
                        $('body').trigger('processStart');
                        return new Promise((resolve, reject) => {
                            return resolve(cartId);
                        }).then(() => {
                            return actions.resolve();
                        }).catch(() => {
                            $('body').trigger('processStop');
                            setSessionMessage($t('Something went wrong'), 'error');
                            return actions.reject();
                        });
                    },

                    /**
                     * On create Order
                     *
                     * 1 - Create Express Payment Order, get the token from the order payment actions.
                     *
                     * @returns {Promise<unknown>}
                     */
                    createOrder: function () {
                        return new Promise((resolve, reject) => {
                            return createExpressPayment(cartId, 'paypal')
                                .done((response) => {
                                    if (response.success === true) {
                                        /* First check get the authorization action */
                                        let paymentAction = _.find(
                                            response.data,
                                            function (action) {
                                                return action.type === 'authorization'
                                            }
                                        );

                                        if (typeof paymentAction === 'undefined'
                                            || !paymentAction.hasOwnProperty('method')
                                            || paymentAction.method !== 'token'
                                        ) {
                                            return reject('PayPal is not available at the moment');
                                        } else {
                                            return resolve(response);
                                        }
                                    } else {
                                        return reject(
                                            response.error_message.length > 0
                                                ? response.error_message
                                                : 'Something went wrong'
                                        );
                                    }
                                }).fail((error) => {
                                    return reject(error);
                                })
                        }).then((response) => {
                            const paymentObject = self.getResponsePaymentObject(response.data);
                            $('body').trigger('processStop');
                            return paymentObject.paymentToken;
                        }).catch((error) => {
                            $('body').trigger('processStop');
                            setSessionMessage($t(error), 'error');
                        });
                    },

                    /**
                     * On PayPal approved,
                     *
                     * 1 - Update cart's billing address, combining shipping & billing data from PayPal.
                     * 2 - Redirect to the checkout page.
                     *
                     * @returns {Promise<unknown>}
                     */
                    onApprove: function (data, actions) {
                        return actions.order.get().then(function (orderData) {
                            /* Set billing to be used for shipping as well. */
                            let shippingAddressPayload = {
                                address: self.getShippingAddressFromOrderData(orderData),
                                addressInformation: {
                                    shipping_address: self.getShippingAddressFromOrderData(orderData)
                                }
                            }, billingAddressPayload = {
                                address: self.getBillingAddressFromOrderData(orderData, shippingAddressPayload),
                                useForShipping: false
                            };

                            return new Promise((resolve, reject) => {
                                if (_.isEmpty(billingAddressPayload)) {
                                    return true;
                                }
                                setCartShippingAddress(cartId, shippingAddressPayload).done(() => {
                                    return setCartBillingAddress(cartId, billingAddressPayload)
                                        .done(() => {
                                            return resolve();
                                        }).fail(() => {
                                            return reject();
                                        });
                                }).fail(() => {
                                    return reject();
                                });
                            }).then(() => {
                                $('body').trigger('processStart');
                                window.location.href = window.checkout.checkoutUrl;
                            }).catch((error) => {
                                console.log(error);
                                setSessionMessage($t('Something went wrong'), 'error');
                            });
                        });
                    },

                    /**
                     * On PayPal cancelled, cancel express payment (if cart is created) and close modal.
                     */
                    onCancel: function () {
                        let bodyElement = $('body');
                        bodyElement.trigger('processStart');

                        if (cartId === null) {
                            bodyElement.trigger('processStop');
                            setSessionMessage($t('You cancelled the payment process'), 'error');
                            return;
                        }

                        $.when(removeExpressPayment(cartId))
                            .done(() => {
                                bodyElement.trigger('processStop');
                                setSessionMessage($t('You cancelled the payment process'), 'error');
                            });

                        cancel.cancelPayment();
                    },

                    /**
                     * On error, display error message in the container.
                     */
                    onError: function () {
                        $('body').trigger('processStop');
                        setSessionMessage($t('Something went wrong'), 'error');
                    },
                }).render(buttonId);
            },

            getResponsePaymentObject: function (response) {
                const paymentObject = {
                        captureUrl: null,
                        cancelUrl: null,
                        paymentToken: null
                    },
                    paymentAction = _.find(response, (action) => {
                        return action.type === 'authorization'
                    }),
                    captureAction = _.find(response, (action) => {
                        return action.type === 'capture'
                    }),
                    cancelAction = _.find(response, (action) => {
                        return action.type === 'cancel'
                    });

                paymentObject.paymentToken = typeof paymentAction !== 'undefined' && paymentAction.method === 'token'
                    ? paymentAction.value
                    : null;
                paymentObject.captureUrl = typeof captureAction !== 'undefined' && captureAction.method === 'redirect_url'
                    ? captureAction.value
                    : null;
                paymentObject.cancelUrl = typeof cancelAction !== 'undefined' && cancelAction.method === 'redirect_url'
                    ? cancelAction.value
                    : null;

                return paymentObject;
            },

            getBillingAddressFromOrderData(orderData, shippingAddress) {
                return {
                    firstname: orderData.payer.name.given_name,
                    lastname: orderData.payer.name.surname,
                    email: orderData.payer.email_address,
                    telephone: shippingAddress.telephone,
                    company: '',
                    street: shippingAddress.street,
                    city: shippingAddress.city,
                    region: shippingAddress.region,
                    postcode: shippingAddress.postcode,
                    country_id: shippingAddress.country_id,
                }
            },

            getShippingAddressFromOrderData(orderData) {
                let address = {
                    firstname: '',
                    lastname: '',
                    email: orderData.payer.email_address,
                    telephone: '',
                    street: [],
                    city: '',
                    region: '',
                    postcode: '',
                    country_id: '',
                }

                /* Return empty object if no shipping property */
                if (orderData.purchase_units.length === 0 ||
                    !orderData.purchase_units[0].hasOwnProperty("shipping")
                ) {
                    return address;
                }

                let shippingFullName =
                    orderData.purchase_units[0].shipping.hasOwnProperty('name') &&
                    orderData.purchase_units[0].shipping.name.hasOwnProperty('full_name')
                        ? orderData.purchase_units[0].shipping.name.full_name
                        : '';
                let shippingFullNameArray = shippingFullName.split(' ');

                address.firstname = shippingFullNameArray.shift();
                if (shippingFullNameArray.length > 0) {
                    address.lastname = shippingFullNameArray.join(' ');
                }

                /* Return object if no address property */
                if (!orderData.purchase_units[0].shipping.hasOwnProperty('address')) {
                    return address;
                }

                const shippingAddress = orderData.purchase_units[0].shipping.address;
                address.street.push(shippingAddress?.address_line_1 ?? '');
                address.street.push(shippingAddress?.address_line_2 ?? '');
                address.city = shippingAddress?.admin_area_2 ?? '';
                address.region = shippingAddress?.admin_area_1 ?? '';
                address.postcode = shippingAddress?.postal_code ?? '';
                address.country_id = shippingAddress?.country_code ?? '';

                return address;
            },
        });
    }
);
