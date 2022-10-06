require(['jquery', 'Magento_Ui/js/modal/alert', 'mage/translate', 'domReady!'], function ($, alert, $t) {
    window.rvvupValidator = function (endpoint) {
        /* Remove previous success message if present */
        if ($(".rvvup-credentials-success-message")) {
            $(".rvvup-credentials-success-message").remove();
        }

        $(this).text($t("We're validating your credentials...")).attr('disabled', true);

        var self = this;
        $.get(endpoint).done(function (res) {
            $('<div class="message message-success rvvup-credentials-success-message">' + res.message + '</div>').insertAfter(self);
        }).fail(function (res) {
            alert({
                title: $t('Rvvup Credential Validation Failed'),
                content: res.responseJSON.message
            });
        }).always(function () {
            $(self).text($t("Validate Credentials")).attr('disabled', false);
        });
    }
});
