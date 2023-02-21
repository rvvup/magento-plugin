define([
    'mage/translate',
    'Magento_Customer/js/customer-data'
], function ($t, customerData) {
    'use strict';

    /**
     * Set message to session message.
     *
     * @param {String} message
     * @return {void}
     */
    return function (message, type) {
        let customerMessages = customerData.get('messages')() || {},
            messages = customerMessages.messages || [],
            messagesContainer = document.getElementsByClassName("page messages");

        messages.push({
            text: $t(message),
            type: type
        });

        customerMessages.messages = messages;

        customerData.set('messages', customerMessages);

        window.scrollTo({
            top: messagesContainer,
            left: 0,
            behavior: 'smooth'
        });

        setTimeout(function() {
            customerData.set('messages', {});
        },6000);
    };
});
