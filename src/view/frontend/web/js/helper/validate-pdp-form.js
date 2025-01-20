define(['jquery'], function ($) {
    'use strict';

    return function (form, clearError) {
        if (!form || !form.length) {
            return false;
        }

        const isValid = $(form).valid();

        if (clearError) {
            $(form).validation('clearError');
        }

        return isValid;
    };
});
