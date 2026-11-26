define([
    'jquery',
    'mage/url',
    'mage/cookies'
], function ($, url, cookies) {
    'use strict';

    /**
     * Create a Rvvup checkout on demand via AJAX.
     *
     * @return {Object} jQuery Deferred resolving to { token, id }
     */
    return function () {
        var payload = {
            form_key: $.mage.cookies.get('form_key')
        };

        url.setBaseUrl(window.BASE_URL || '/');

        return $.ajax({
            url: url.build('rvvup/checkout/createOnDemand'),
            type: 'POST',
            data: JSON.stringify(payload),
            contentType: 'application/json',
            dataType: 'json'
        }).then(function (response) {
            if (response.success && response.data) {
                return response.data;
            }
            return $.Deferred().reject(response.error_message || 'Failed to create checkout').promise();
        });
    };
});
