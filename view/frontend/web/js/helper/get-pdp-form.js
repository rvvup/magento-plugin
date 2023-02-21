define(function () {
    'use strict';

    /**
     * @param {Element} element
     */
    return function (element) {
        return element.closest('form#product_addtocart_form');
    };
});
