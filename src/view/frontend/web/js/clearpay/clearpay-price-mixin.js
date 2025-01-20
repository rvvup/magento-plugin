define(['mage/utils/wrapper'],
    function (wrapper) {
        'use strict';

        return function (superEstimateTotals) {
            superEstimateTotals.estimateTotals = wrapper.wrapSuper(superEstimateTotals.estimateTotals, function (address) {
                this._super(address).done(function (result) {
                    let clearpaySummaryElement = document.getElementById('clearpay-summary');

                    if (clearpaySummaryElement !== null && clearpaySummaryElement.length !== 0) {
                        clearpaySummaryElement.dataset.amount = result['base_grand_total'];
                    }
                });
            });
            return superEstimateTotals;
        };
    });
