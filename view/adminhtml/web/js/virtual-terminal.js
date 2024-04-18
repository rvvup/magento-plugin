define(['jquery', 'mage/url'], function($) {
    'use strict';

    return {
        insertPaymentLink: function (url, amount, store_id, currency_code, order_id) {
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
                    document.getElementById('rvvup-virtual-terminal').disabled = false;
                    if (data.success !== true) {
                        alert('Failed to get terminal link, please check logs')
                        return;
                    }

                    if (data['iframe-url']) {
                       var iframe = document.getElementById('rvvup_iframe');
                       iframe.src = data['iframe-url'];
                       iframe.style.display = 'block';
                       iframe.scrollIntoView();
                    } else {
                        alert('Failed to get terminal link, please check logs');
                    }
                },
                error: function () {
                    document.getElementById('rvvup-virtual-terminal').disabled = false;
                    alert('Failed to create terminal link, please check logs');
                }
            })
        }
    };
});
