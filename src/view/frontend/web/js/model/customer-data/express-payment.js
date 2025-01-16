define(['Magento_Customer/js/customer-data'], function (customerData) {
    'use strict';

    return {
        /**
         * Get current session store code.
         *
         * @returns {string}
         */
        getStoreCode: function() {
            const expressPaymentData = customerData.get('rvvup-express-payment')();

            return expressPaymentData.store_code ? expressPaymentData.store_code : null;
        },

        /**
         * Get current session quote id. Masked ID if Guest user.
         *
         * @returns {string}
         */
        getQuoteId: function () {
            const expressPaymentData = customerData.get('rvvup-express-payment')();

            return expressPaymentData.quote_id ? expressPaymentData.quote_id : null;
        },

        /**
         * Get whether the current session user is logged in or not.
         *
         * @returns {boolean}
         */
        getIsLoggedIn: function () {
            const expressPaymentData = customerData.get('rvvup-express-payment')();

            return expressPaymentData.is_logged_in ? expressPaymentData.is_logged_in : null;
        },

        /**
         * Whether the current session quote is for an express Payment.
         *
         * @return {boolean}
         */
        isExpressPayment: function() {
            const expressPaymentData = customerData.get('rvvup-express-payment')();

            return expressPaymentData.is_express_payment ? expressPaymentData.is_express_payment : false;
        }
    };
});
