define([
    'mage/storage'
], function (storage) {
    'use strict'
    return function (config, element) {
        storage.get('/rest/' + config.storeCode + '/V1/rvvup/clearpay')
            .done(function(res) {
                if (res === true) {
                    let clearpaySummaryElement = document.getElementById('clearpay-summary');

                    if (clearpaySummaryElement !== null && clearpaySummaryElement.length !== 0) {
                        clearpaySummaryElement.removeAttribute('style');
                    }
                }
            })
    }
});
