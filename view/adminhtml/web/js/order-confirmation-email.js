define(['jquery'], function($) {
    'use strict';

    return {
        disableSendingConfirmation: function () {
            var checkbox = document.getElementById('send_confirmation');
            if (checkbox) {
                checkbox.value = '0';
                checkbox.checked = false;
                checkbox.disabled = true;
                $('label[for="send_confirmation"]').first()
                    .text("Order Confirmation email is always sent for Rvvup payment methods");
            }
        }
    };
});
