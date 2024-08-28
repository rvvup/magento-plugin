define(['jquery'], function($) {
    'use strict';

    return {
        disableSendingConfirmation: function () {
            var checkbox = document.getElementById('send_confirmation');
            if (checkbox) {
                checkbox.value = '0';
                checkbox.checked = false;
                checkbox.disabled = true;
                var element = ($('label[for="send_confirmation"]'));
                if (element && element[0]) {
                    element[0].innerText = 'Order Confirmation email is always sent for Rvvup payment methods';
                }
            }
        }
    };
});
