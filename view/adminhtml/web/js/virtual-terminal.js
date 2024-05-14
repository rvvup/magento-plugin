define(['jquery', 'Magento_Ui/js/modal/alert'], function($, alert) {
    'use strict';

    return {
        createVirtualTerminal: function (url, amount, store_id, currency_code, order_id) {
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
                        alert({
                            content: 'Failed to get terminal link, please check logs'
                        });
                        return;
                    }

                    if (data['iframe-url']) {
                        let fetchCheckoutUrl = function () {
                         return Promise.resolve(
                             data['iframe-url']
                         );
                        }
                        const rvvup = Rvvup();
                        const checkout = rvvup.createEmbeddedCheckout({fetchCheckoutUrl})
                            .then((checkout) => {
                                checkout.mount()
                            }
                            ).finally(() => {
                                document.getElementById('rvvup-virtual-terminal').disabled = false;
                            });
                    } else {
                        alert({
                            content: 'Failed to get terminal link, please check logs'
                        });
                    }
                },
                error: function () {
                    document.getElementById('rvvup-virtual-terminal').disabled = false;
                    alert({
                        content: 'Failed to create terminal link, please check logs'
                    });
                }
            })
        }
    };
});
