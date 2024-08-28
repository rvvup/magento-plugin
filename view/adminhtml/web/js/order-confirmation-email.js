define(['jquery'], function($) {
    'use strict';

    return {
        disableSendingConfirmation: function () {
            var checkbox = document.getElementById('send_confirmation');
            if (checkbox) {
                checkbox.value = '0';
                checkbox.checked = false;
            }
        }
    };
});
