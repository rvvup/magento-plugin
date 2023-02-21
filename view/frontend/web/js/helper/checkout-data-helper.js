define([
    'jquery',
    'Magento_Customer/js/customer-data'
], function ($, storage) {
    'use strict';

    var cacheKey = 'checkout-data';

    return {
        /**
         * @param {Object} data
         */
        saveData: function (data) {
            storage.set(cacheKey, data);
        },

        /**
         * @return {*}
         */
        initData: function () {
            return {
                'selectedShippingAddress': null, //Selected shipping address pulled from persistence storage
                'shippingAddressFromData': null, //Shipping address pulled from persistence storage
                'newCustomerShippingAddress': null, //Shipping address pulled from persistence storage for customer
                'selectedShippingRate': null, //Shipping rate pulled from persistence storage
                'selectedPaymentMethod': null, //Payment method pulled from persistence storage
                'selectedBillingAddress': null, //Selected billing address pulled from persistence storage
                'billingAddressFromData': null, //Billing address pulled from persistence storage
                'newCustomerBillingAddress': null //Billing address pulled from persistence storage for new customer
            };
        },

        /**
         * @return {*}
         */
        getData: function () {
            var data = storage.get(cacheKey)();

            if ($.isEmptyObject(data)) {
                data = $.initNamespaceStorage('mage-cache-storage').localStorage.get(cacheKey);

                if ($.isEmptyObject(data)) {
                    data = initData();
                    saveData(data);
                }
            }

            return data;
        },

        setRvvupBillingAddress: function (data) {
            var obj = this.getData();

            obj.rvvupBillingAddress = data;
            this.saveData(obj);
        },

        getRvvupBillingAddress: function () {
            return this.getData().rvvupBillingAddress;
        },
    }
});
