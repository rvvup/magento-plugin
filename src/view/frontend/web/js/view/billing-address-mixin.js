define([
    'Magento_Checkout/js/model/quote',
    'Rvvup_Payments/js/helper/checkout-data-helper',
    'Rvvup_Payments/js/helper/is-express-payment',
],function (quote, checkoutDataHelper, isExpressPayment) {
    'use strict';

    var mixin = {
        initObservable: function () {
            this._super();

            var paymentMethod = quote.paymentMethod();

            if (paymentMethod &&
                typeof this.dataScopePrefix !== 'undefined' &&
                this.dataScopePrefix.includes(paymentMethod.method)
            ) {
                quote.billingAddress.subscribe(function (newAddress) {
                    if (isExpressPayment() && quote.isVirtual()) {
                        checkoutDataHelper.setRvvupBillingAddress(newAddress);
                    }
                }, this);
            }

            return this;
        }
    };

    return function (target) {
        return target.extend(mixin);
    };
});
