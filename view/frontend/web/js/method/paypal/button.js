define(
    [
        'uiRegistry',
        'uiComponent',
        'jquery',
        'underscore',
        'mage/storage',
        'mage/translate',
        'Rvvup_Payments/js/action/add-to-cart',
        'Rvvup_Payments/js/action/create-cart',
        'Rvvup_Payments/js/action/create-express-payment',
        'Rvvup_Payments/js/action/empty-cart',
        'Rvvup_Payments/js/action/remove-express-payment',
        'Rvvup_Payments/js/action/set-cart-billing-address',
        'Rvvup_Payments/js/action/set-session-message',
        'Rvvup_Payments/js/helper/get-add-to-cart-payload',
        'Rvvup_Payments/js/helper/get-current-quote-id',
        'Rvvup_Payments/js/helper/get-paypal-pdp-button-style',
        'Rvvup_Payments/js/helper/get-pdp-form',
        'Rvvup_Payments/js/helper/is-paypal-pdp-button-enabled',
        'Rvvup_Payments/js/helper/validate-pdp-form',
        'Rvvup_Payments/js/method/paypal/cancel',
        'domReady!'
    ],
    function (
        registry,
        Component,
        $,
        _,
        storage,
        $t,
        addToCart,
        createCart,
        createExpressPayment,
        emptyCart,
        removeExpressPayment,
        setCartBillingAddress,
        setSessionMessage,
        getAddToCartPayload,
        getCurrentQuoteId,
        getPayPalPdpButtonStyle,
        getPdpForm,
        isPayPalPdpButtonEnabled,
        validatePdpForm,
        cancel
    ) {
        'use strict';
        return Component.extend({
            defaults: {
                buttonId: null,
                cartId: null
            },
            /**
             * Component initialization function.
             *
             * We expect the buttonId to be provided when component is initialized, if none, no action.
             * Also, no action if paypal button is disabled on pdp from Rvvup.
             */
            initialize: function (config) {
                this.buttonId = config.buttonId;

                if (this.buttonId === null || !isPayPalPdpButtonEnabled()) {
                    return this;
                }

                this.renderPayPalButton(this.buttonId);

                return this;
            },
            /**
             * Instantiate PayPal Button on the related container.
             *
             * If button element does not exist or already has children elements (PayPal already loaded), no action.
             *
             * @param buttonId
             */
            renderPayPalButton: function (buttonId) {
                let self = this,
                    buttonElement = document.getElementById(buttonId);

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

                let form = getPdpForm(buttonElement);

                /* No action if no form is found */
                if (form.length === 0) {
                    return;
                }

                rvvup_paypal.Buttons({
                    style: getPayPalPdpButtonStyle(),
                    /**
                     * On PayPal button click instantiate and validate the steps allowing for errors.
                     * 1 - Validate form data
                     * 2 - Empty existing quote or create new quote
                     * 3 - Add to cart
                     * 4 - Create express payment
                     *
                     * Use async validation as per PayPal button docs
                     * @see https://developer.paypal.com/docs/checkout/standard/customize/validate-user-input/
                     *
                     * @param data
                     * @param actions
                     * @returns {Promise<never>|*}
                     */
                    onClick: function (data, actions) {
                        $('body').trigger('processStart');
                        return new Promise((resolve, reject) => {
                            return getCurrentQuoteId() === null
                                ? createCart()
                                    .done((cartId) => {
                                        self.cartId = cartId;

                                        return resolve(cartId);
                                    })
                                : emptyCart(getCurrentQuoteId())
                                    .done((cartId) => {
                                        self.cartId = cartId;

                                        return resolve(cartId);
                                    });
                        }).then((cartId) => {
                            if (!validatePdpForm(form)) {
                                $('body').trigger('processStop');
                                return actions.reject();
                            } else {
                                return addToCart(cartId, getAddToCartPayload(form, cartId)).done(() => {
                                    return actions.resolve();
                                });
                            }
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
                            return createExpressPayment(self.cartId, 'paypal')
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
                                address: self.getShippingAddressFromOrderData(orderData)
                            }, billingAddressPayload = {
                                address: self.getBillingAddressFromOrderData(orderData, shippingAddressPayload),
                                useForShipping: true
                            };

                            return new Promise((resolve, reject) => {
                                if (_.isEmpty(billingAddressPayload)) {
                                    return true;
                                }

                                return setCartBillingAddress(self.cartId, billingAddressPayload)
                                    .done(() => {
                                        return resolve();
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

                        if (self.cartId === null) {
                            bodyElement.trigger('processStop');
                            setSessionMessage($t('You cancelled the payment process'), 'error');
                            return;
                        }

                        $.when(removeExpressPayment(self.cartId))
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
                }).render('#' + buttonId);
            },
            /**
             * Get a structured Payment Object from the Express Payment create response.
             *
             * @param response
             * @return {Object}
             */
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
            /**
             * Get Billing address request data from PayPal order data & already set shipping address data.
             *
             * @param {Object} orderData
             * @param {Object} shippingAddress
             * @return {Object}
             */
            getBillingAddressFromOrderData: function (orderData, shippingAddress) {
                return {
                    firstname: orderData.payer.name.given_name,
                    lastname: orderData.payer.name.surname,
                    email: orderData.payer.email_address,
                    telephone: shippingAddress.address.telephone,
                    company: '',
                    street: shippingAddress.address.street,
                    city: shippingAddress.address.city,
                    region: shippingAddress.address.region,
                    postcode: shippingAddress.address.postcode,
                    country_id: shippingAddress.address.country_id,
                }
            },
            /**
             * Get Shipping address request data from PayPal order data.
             *
             * @param {Object} orderData
             * @return {Object}
             */
            getShippingAddressFromOrderData: function (orderData) {
                let address = {
                    firstname: '',
                    lastname: '',
                    email: '',
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

                address.street.push(orderData.purchase_units[0].shipping.address.hasOwnProperty('address_line_1')
                    ? orderData.purchase_units[0].shipping.address.address_line_1
                    : ''
                );

                address.street.push(orderData.purchase_units[0].shipping.address.hasOwnProperty('address_line_2')
                    ? orderData.purchase_units[0].shipping.address.address_line_2
                    : ''
                );

                address.city = orderData.purchase_units[0].shipping.address.hasOwnProperty('admin_area_2')
                    ? orderData.purchase_units[0].shipping.address.admin_area_2
                    : '';

                address.region = orderData.purchase_units[0].shipping.address.hasOwnProperty('admin_area_1')
                    ? orderData.purchase_units[0].shipping.address.admin_area_1
                    : '';

                address.postcode = orderData.purchase_units[0].shipping.address.hasOwnProperty('postal_code')
                    ? orderData.purchase_units[0].shipping.address.postal_code
                    : '';

                address.country_id = orderData.purchase_units[0].shipping.address.hasOwnProperty('country_code')
                    ? orderData.purchase_units[0].shipping.address.country_code
                    : '';

                return address;
            }
        });
    }
);
