define([
    'jquery',
], function ($) {
    'use strict';

    let priceType;

    return function (priceBundle) {
        $.widget('mage.priceBundle', $['mage']['priceBundle'], {
            _init: function () {
                this._super();

                let priceBox = $(this.options.priceBoxSelector, this.element),
                    dataPriceTypeContainer = priceBox.find('[data-price-type]').first();

                /* Get the price type loaded for the element on the bundle product page */
                priceType = dataPriceTypeContainer.length === 1 && dataPriceTypeContainer.data('price-type') !== null ?
                    dataPriceTypeContainer.data('price-type')
                    : 'finalPrice';

                priceBox.on('priceUpdated', this._updateClearpayPriceData.bind(this));
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
                if (
                    !data ||
                    !data[priceType] ||
                    !data[priceType].amount
                ) {
                    return this;
                }

                if (
                    data[priceType].amount <= rvvupMinPrice ||
                    data[priceType].amount >= rvvupMaxPrice
                ) {
                    $('.clearpay').hide();

                    return this;
                }

                document.getElementById('clearpay-summary').dataset.amount = data[priceType].amount;
                $('.clearpay').show();

                return this;
            }
        });

        return $['mage']['priceBundle'];
    };
});
