define(['jquery', 'Magento_Ui/js/modal/alert'], function($, alert) {
    'use strict';

    return {
        createPaymentLink: function (url, amount, store_id, currency_code, order_id) {
            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    'amount' : amount,
                    'store_id' : store_id,
                    'currency_code' : currency_code,
                    'order_id' : order_id,
                },
                success: function (data, status, xhr) {
                    document.getElementById('rvvup-payment-link').disabled = false;
                    if (data.success !== true) {
                        document.getElementById('rvvup-payment-link').disabled = false;
                        if (data.message) {
                            alert({
                                content: data.message
                            });
                        } else {
                            alert({
                                content: 'Failed to create payment link, please check logs'
                            });
                        }
                        return;
                    }
                    alert({
                        content: 'Successfully created and sent via email!'
                    })
                    window.location.reload();
                },
                error: function () {
                    document.getElementById('rvvup-payment-link').disabled = false;
                    alert({
                        content: 'Failed to create payment link, please check logs'
                    });
                }
            })
        }
    };
});
