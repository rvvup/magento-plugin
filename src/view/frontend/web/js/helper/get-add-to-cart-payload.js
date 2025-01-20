define(['Rvvup_Payments/js/helper/get-form-data'], function (getFormData) {
    'use strict';

    /**
     * @param {Element} form
     * @param {string} cartId
     * @return {Object}
     */
    return function (form, cartId) {
        const formData = getFormData(form);

        let itemData = {
            sku: formData.productSku,
            qty: formData.qty,
            quote_id: cartId
        };

        if (formData.super_attribute) {
            let optionsArray = [];
            for (const option in formData.super_attribute) {
                optionsArray.push({
                    option_id: option,
                    option_value: formData.super_attribute[option]
                });
            }
            itemData.product_type = "configurable";
            itemData.product_option = {
                extension_attributes: {
                    configurable_item_options: optionsArray
                }
            };
        }

        return {
            cartItem: itemData
        };
    };
});
