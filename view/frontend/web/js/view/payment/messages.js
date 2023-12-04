define([
    'uiComponent',
    'jquery',
    'underscore',
    'mage/storage',
    'mage/translate',
    'Magento_Checkout/js/model/url-builder',
    'Magento_Ui/js/model/messageList',
    'domReady!',
], function (Component, $, _, storage, $t, urlBuilder, globalMessages) {
    return Component.extend({
        /**
         * Initialize component.
         *
         * @returns {*}
         */
        initialize: function () {
            this._super();

            this.initComponentMessages();

            return this;
        },

        /**
         *
         */
        initComponentMessages: function() {
            let self = this;
            $.when(self.getMessages())
                .done(function(data) {
                    _.each(data, function(message) {
                        switch(message.type) {
                            case 'error':
                                globalMessages.addErrorMessage({
                                    'message': message.text
                                })
                                break;
                            case 'notice':
                                globalMessages.addSuccessMessage({
                                    'message': message.text
                                })
                                break;
                            case 'success':
                                globalMessages.addSuccessMessage({
                                    'message': message.text
                                })
                                break;
                            case 'warning':
                                globalMessages.addErrorMessage({
                                    'message': message.text
                                })
                                break;
                        }

                        if (data.length > 0) {
                            self.initClearComponentMessagesTimeOut();
                        }
                    });
                });
        },
        /**
         * Initialize a timeout to clear the messages from the relevant component.
         */
        initClearComponentMessagesTimeOut: function() {
            setTimeout(() => {
                globalMessages.clear();
            }, 6000);
        },
        /**
         * Get the Message container messages from the API
         * @returns {*}
         */
        getMessages: function() {
            let serviceUrl = urlBuilder.createUrl('/rvvup/payments/session/messages', {});

            return storage.get(
                serviceUrl,
                true,
                'application/json'
            ).done(function (data) {
                return data;
            }).fail(function(response) {
                globalMessages.addErrorMessage({
                    'message': $t('Failed to load checkout messages')
                });
            });
        }
    })
});
