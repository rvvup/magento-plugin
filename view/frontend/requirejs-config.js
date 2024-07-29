var config = {
    config: {
        mixins: {
            "Magento_Swatches/js/swatch-renderer": {
                'Rvvup_Payments/js/swatch-renderer-mixin' : true
            },
            "Magento_Bundle/js/price-bundle": {
                'Rvvup_Payments/js/bundle/price-bundle-mixin' : true
            },
            "Magento_Checkout/js/model/cart/totals-processor/default": {
                'Rvvup_Payments/js/clearpay/clearpay-price-mixin' : true
            },
            'Magento_Checkout/js/model/checkout-data-resolver': {
                'Rvvup_Payments/js/checkout-data-resolver-mixin': true
            },
            'Magento_Checkout/js/view/billing-address': {
                'Rvvup_Payments/js/view/billing-address-mixin': true
            },
            'Magento_Checkout/js/action/place-order': {
                'Rvvup_Payments/js/action/place-order-mixin': true
            },
            'Rvvup_Payments/js/view/payment/methods/rvvup-paypal': {
                'Rvvup_Payments/js/view/payment/methods/rvvup-paypal-firecheckout-compatibility-hook': true
            }
        }
    },
    map: {
        '*': {
            'cardPayment': 'Rvvup_Payments/js/method/pay-by-card/card'
        }
    },
    paths: {
        cardPayment: 'Rvvup_Payments/js/method/pay-by-card/card',
    },
};
