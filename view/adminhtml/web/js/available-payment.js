define([
    'jquery',
    'uiComponent',
    'Magento_Ui/js/modal/alert',
], function ($, Class, alert) {
    'use strict';

    return Class.extend({
        defaults: {
            method: null,
            store_id: null,
            currency_code: null,
            amount: null,
            url: null
        },

        initObservable: function () {
            var self = this;

            document.getElementById('p_method_' + this.method)
                .addEventListener('change', function () {
                    self.isAvailable(function (available) {
                        if (!available) {
                            self.warn();
                        }
                    });
                });

            return this;
        },
        isAvailable: function (callback) {
            $.ajax({
                type: 'POST',
                url: this.url,
                data: {
                    method: this.method,
                    store_id: this.store_id,
                    currency_code : this.currency_code,
                    amount : this.amount
                },
                showLoader: true
            }).done(function (res) {
                return callback(res.available);
            }).fail(function () {
                return callback(false);
            })
        },
        warn: function () {
            var title = 'Payment method unavailable';

            if (this.method === 'rvvup_payment-link') {
                var email = '<a href="mailto:support@rvvup.com">support@rvvup.com</a>';
                var message = 'We’re sorry but your currently enabled Rvvup payment methods don’t support Payment Links'
                message += ' - please contact ' + email + ' to discuss other payment methods we support.';

                alert({
                    content: $.mage.__(message),
                    title: $.mage.__(title)
                });
            } else if (this.method === 'rvvup_virtual-terminal') {
                var email = '<a href="mailto:support@rvvup.com">support@rvvup.com</a>';
                var message = 'We’re sorry but you need Rvvup Cards and a MOTO MID to use Virtual Terminal'
                message += ' - please contact ' + email + ' to set this up';

                alert({
                    content: $.mage.__(message),
                    title: $.mage.__(title)
                });
            }
        },
    });
});
