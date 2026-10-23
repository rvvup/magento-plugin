define([
        'Rvvup_Payments/js/view/payment/method-renderer/rvvup-method',
    'ko',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/action/set-shipping-information',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/error-processor',
    'Rvvup_Payments/js/action/checkout/payment/create-payment-session',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Rvvup_Payments/js/view/payment/methods/place-order-helpers',
    'Rvvup_Payments/js/model/checkout/payment/card-appearance',
    'mage/translate',

    'domReady!'
    ], function (
        Component,
        ko,
        loader,
        setShippingInformation,
        quote,
        customer,
        errorProcessor,
        createPaymentSession,
        additionalValidators,
        placeOrderHelpers,
        cardAppearance,
        $t,
    ) {
        'use strict';

    // Without the SDK (blocked or failed script) or a checkout token, don't offer card.
    if (!window.rvvup_sdk || !rvvup_parameters.checkout || !rvvup_parameters.checkout.token) {
        console.error("Card not loaded as the Rvvup SDK or the checkout token is missing.");
        return Component.extend({
            canRender: function () {
                return false;
            },
        });
    }

    function getQuoteTotal()
    {
        let quoteTotals = quote.totals();
        let total = {amount: "0", currency: "GBP"};
        if (!quoteTotals) {
            return total;
        }
        if (quoteTotals.grand_total) {
            total.amount = quoteTotals.grand_total.toString();
        }
        if (quoteTotals.quote_currency_code) {
            total.currency = quoteTotals.quote_currency_code;
        }
        return total;

    }

    let available = ko.observable(true);

    /**
     * Retry before hiding the method so a transient failure at page load does not
     * take card away from the shopper for the whole checkout session.
     */
    function createCardPaymentMethod(remainingRetries) {
        return window.rvvup_sdk.createPaymentMethod("CARD", {
            checkoutSessionKey: rvvup_parameters.checkout.token,
            total: getQuoteTotal(),
            appearance: cardAppearance(),
        }).catch(e => {
            if (remainingRetries > 0) {
                return new Promise(resolve => setTimeout(resolve, 2000))
                    .then(() => createCardPaymentMethod(remainingRetries - 1));
            }
            available(false);
            console.error("Error creating Card payment method", e);
        });
    }

    let cardPromise = createCardPaymentMethod(2);

    /**
     * The SDK accumulates event listeners and fires "ready" only once, while Magento can
     * re-create the renderer whenever the payment list re-renders. Handlers are therefore
     * registered exactly once here and delegate to the active component instance, and the
     * one-shot ready event is captured in a promise so every render can await it and mount.
     */
    let activeComponent = null;

    /**
     * The shopper waits on the payment from the moment they submit the card until they are
     * redirected, but the create payment session call takes the loader down again as soon as it
     * returns and the SDK then spends several seconds on the 3DS device check before it shows a
     * challenge, leaving the checkout looking idle. The loader is therefore held from here for
     * the whole journey and only taken down while the challenge is asking for shopper input.
     *
     * Magento's loader is reference counted, so it is held through a single outstanding
     * start/stop pair, which lets the create payment session call raise and drop its own count
     * underneath without the loader ever disappearing.
     */
    let paymentInProgress = false;
    let loaderHeld = false;

    function holdLoader() {
        if (loaderHeld) {
            return;
        }
        loaderHeld = true;
        loader.startLoader();
    }

    function releaseLoader() {
        if (!loaderHeld) {
            return;
        }
        loaderHeld = false;
        loader.stopLoader(true);
    }

    function startPayment() {
        paymentInProgress = true;
        holdLoader();
    }

    function endPayment() {
        paymentInProgress = false;
        releaseLoader();
    }

    /**
     * The SDK renders the 3DS challenge into #challengeFrameContainer and emits no event for
     * it, so the container is watched directly.
     */
    function isChallengeOpen() {
        return document.getElementById('challengeIframe') !== null;
    }

    let challengeOpen = false;

    new MutationObserver(function () {
        if (isChallengeOpen() === challengeOpen) {
            return;
        }
        challengeOpen = !challengeOpen;
        if (challengeOpen) {
            releaseLoader();
            return;
        }
        if (paymentInProgress) {
            holdLoader();
        }
    }).observe(document.body, {childList: true, subtree: true});

    let cardReadyPromise = cardPromise.then(function (card) {
        return new Promise(function (resolve) {
            if (!card) {
                return;
            }
            card.on("ready", () => resolve(card));
        });
    });

    cardPromise.then(function (card) {
        if (!card) {
            return;
        }
        card.on("validate", async () => {
            let valid = placeOrderHelpers.validate(activeComponent, additionalValidators);
            if (!valid) {
                endPayment();
                activeComponent.formReady(true);
            }
            return valid;
        });
        card.on("beforePaymentAuth", async () => {
            return await activeComponent.beforePayment(activeComponent)
        });
        card.on("paymentAuthorized", () => {
            activeComponent.paymentAuthorized();
        });
        card.on("paymentCaptured", () => {
            activeComponent.paymentAuthorized();
        });
        card.on("paymentFailed", (data) => {
            endPayment();
            activeComponent.formReady(true);
            errorProcessor.process({
                    responseText: JSON.stringify({
                        message: $t('Payment %1').replace('%1', data && data.code ? data.code : $t('failed'))
                    })
                },
                activeComponent.messageContainer);
        });
        card.on("error", (err) => {
            console.error("Card payment error", err);
            endPayment();
            activeComponent.formReady(true);
            errorProcessor.process({
                    responseText:
                        JSON.stringify({message: $t('Something went wrong processing the card payment.')})
                },
                activeComponent.messageContainer);
        });
    });

    let $redirectUrl = null;

        return Component.extend({
            defaults: {
                templates: {
                    rvvupPlaceOrderTemplate: 'Rvvup_Payments/payment/method/card/place-order',
                },
            },

            initialize: function () {
                this._super();
                this.formReady = ko.observable(false);
                return this;
            },

            canRender: function () {
                return available;
            },

            mountCardForm: function () {
                let self = this;
                activeComponent = this;
                cardReadyPromise.then(async function (card) {
                    card.update({paymentRequest: {total: getQuoteTotal()}});
                    await card.mount({selector: "#rvvup-card-form-container"});
                    self.formReady(true);
                });
            },

            submitCard: function () {
                if (!this.formReady() || !placeOrderHelpers.validate(this, additionalValidators)) {
                    return;
                }
                this.formReady(false);
                startPayment();
                cardPromise.then(function (card) {
                    card.update({paymentRequest: {total: getQuoteTotal()}});
                    card.submit();
                });
            },

            populateGuestEmail: function () {
                if (customer.isLoggedIn() || quote.guestEmail) {
                    return;
                }
                let email = document.getElementById('customer-email');
                if (email) {
                    quote.guestEmail = email.value;
                }
            },

            beforePayment: async function (component) {
                try {
                    component.populateGuestEmail();
                    if (placeOrderHelpers.shouldSaveShippingInformation()) {
                        await setShippingInformation();
                    }
                    const response = await createPaymentSession(
                        component.messageContainer,
                        rvvup_parameters.checkout.id,
                        component.getData()
                    );

                    $redirectUrl = response.redirect_url;
                    return {paymentSessionId: response.payment_session_id};
                } catch (e) {
                    errorProcessor.process({
                            responseText:
                                JSON.stringify({message: $t('Error creating payment, %1').replace('%1', e)})
                        },
                        component.messageContainer);
                    console.error("Error creating payment", e);
                    endPayment();
                    component.formReady(true);
                    return false;
                }
            },

            paymentAuthorized: function () {
                loader.startLoader();
                window.location.href = $redirectUrl;
            }
        });


    }
);
