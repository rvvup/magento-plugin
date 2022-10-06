define([
    'jquery',
    'priceUtils',
    'mage/template'
], function ($,priceUtils,mageTemplate) {
    'use strict';

    return function (SwatchRenderer) {
        $.widget('mage.SwatchRenderer', $['mage']['SwatchRenderer'], {
            _init: function () {
               this._super();
                var $widget = this,
                    newPrices = $widget.options.jsonConfig.prices,
                    rvvupMinPrice = $widget.options.rvvupMin,
                    rvvupMaxPrice = $widget.options.rvvupMax;

                if (newPrices.finalPrice.amount < rvvupMinPrice || newPrices.finalPrice.amount > rvvupMaxPrice
                || newPrices.finalPrice.amount === rvvupMinPrice || newPrices.finalPrice.amount === rvvupMaxPrice) {
                    $('.clearpay').hide();
                }
            },

            /**
             * Get new prices for selected options
             *
             * @returns {*}
             * @private
             */
            _getNewPrices: function () {
                var $widget = this,
                    newPrices = $widget.options.jsonConfig.prices,
                    allowedProduct = this._getAllowedProductWithMinPrice(this._CalcProducts());

                if (!_.isEmpty(allowedProduct)) {
                    newPrices = this.options.jsonConfig.optionPrices[allowedProduct];
                }

                return newPrices;
            },

            /**
             * Update total price
             *
             * @private
             */
            _UpdatePrice: function () {
                var $widget = this,
                    $product = $widget.element.parents($widget.options.selectorProduct),
                    $productPrice = $product.find(this.options.selectorProductPrice),
                    result = $widget._getNewPrices(),
                    tierPriceHtml,
                    isShow,
                    rvvupMinPrice = $widget.options.rvvupMin,
                    rvvupMaxPrice = $widget.options.rvvupMax;

                if (result.finalPrice.amount < rvvupMinPrice || result.finalPrice.amount > rvvupMaxPrice
                || result.finalPrice.amount === rvvupMaxPrice || result.finalPrice.amount === rvvupMinPrice) {
                    $('.clearpay').hide();
                } else  {
                    document.getElementById('clearpay-summary').dataset.amount = result.finalPrice.amount;
                    $('.clearpay').show();
                }

                $productPrice.trigger(
                    'updatePrice',
                    {
                        'prices': $widget._getPrices(result, $productPrice.priceBox('option').prices)
                    }
                );

                isShow = typeof result != 'undefined' && result.oldPrice.amount !== result.finalPrice.amount;

                $productPrice.find('span:first').toggleClass('special-price', isShow);

                $product.find(this.options.slyOldPriceSelector)[isShow ? 'show' : 'hide']();

                if (typeof result != 'undefined' && result.tierPrices && result.tierPrices.length) {
                    if (this.options.tierPriceTemplate) {
                        tierPriceHtml = mageTemplate(
                            this.options.tierPriceTemplate,
                            {
                                'tierPrices': result.tierPrices,
                                '$t': $t,
                                'currencyFormat': this.options.jsonConfig.currencyFormat,
                                'priceUtils': priceUtils
                            }
                        );
                        $(this.options.tierPriceBlockSelector).html(tierPriceHtml).show();
                    }
                } else {
                    $(this.options.tierPriceBlockSelector).hide();
                }

                $(this.options.normalPriceLabelSelector).hide();

                _.each($('.' + this.options.classes.attributeOptionsWrapper), function (attribute) {
                    if ($(attribute).find('.' + this.options.classes.optionClass + '.selected').length === 0) {
                        if ($(attribute).find('.' + this.options.classes.selectClass).length > 0) {
                            _.each($(attribute).find('.' + this.options.classes.selectClass), function (dropdown) {
                                if ($(dropdown).val() === '0') {
                                    $(this.options.normalPriceLabelSelector).show();
                                }
                            }.bind(this));
                        } else {
                            $(this.options.normalPriceLabelSelector).show();
                        }
                    }
                }.bind(this));
            },
        });
        return $['mage']['SwatchRenderer'];
    };
})
