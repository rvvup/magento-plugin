define([
    'jquery',
], function ($) {
    'use strict';

    let priceType;

    return function (priceBundle) {
        $.widget('mage.priceBundle', $['mage']['priceBundle'], {
            _init: function () {
                let priceBox = $(this.options.priceBoxSelector, this.element),
                    dataPriceTypeContainer = priceBox.find('[data-price-type]').first();

                /* Get the price type loaded for the element on the bundle product page */
                priceType = dataPriceTypeContainer.length === 1 && dataPriceTypeContainer.data('price-type') !== null
                    ? dataPriceTypeContainer.data('price-type')
                    : 'finalPrice';

                priceBox.on('priceUpdated', this._updateClearpayPriceData.bind(this));
                this._super();
            },
            /**
             * Update clearpay price data so message box can be updated.
             *
             * @returns {priceBundle}
             * @private
             */
            _updateClearpayPriceData: function (event, data) {
                let $widget = this,
                    rvvupMinPrice = $widget.options.rvvupMin,
                    rvvupMaxPrice = $widget.options.rvvupMax;

                /* If price is not set, no action */
                if (!data || !data[priceType] || !data[priceType].amount) {
                    return this;
                }

                let clearpayElement = $('.clearpay');

                if (data[priceType].amount <= rvvupMinPrice || data[priceType].amount >= rvvupMaxPrice) {
                    clearpayElement.hide();

                    return this;
                }

                let clearpaySummaryElement = document.getElementById('clearpay-summary');

                if (clearpaySummaryElement !== null && clearpaySummaryElement.length !== 0) {
                    clearpaySummaryElement.dataset.amount = data[priceType].amount;
                }

                clearpayElement.show();

                return this;
            }
        });

        return $['mage']['priceBundle'];
    };
});
