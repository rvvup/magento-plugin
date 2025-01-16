define(
    ['jquery', 'mage/url'],
    function (
        $, url
    ) {
        'use strict';
        return {
            cancelPayment: function () {
                $.ajax({
                    url: url.build("rvvup/express/cancel"),
                    type: 'GET',
                    success: function (data, status, xhr) {
                        if (data.success !== true) {
                            console.log('Error cancelling Rvvup payment');
                        }
                    },
                    error: function () {
                        console.log('Error cancelling Rvvup payment');
                    }
                });
            }
        }
    });
