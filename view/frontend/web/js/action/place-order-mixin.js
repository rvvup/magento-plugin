define([
	'mage/utils/wrapper',
	'Magento_Checkout/js/model/shipping-save-processor/default',
	'jquery',
], function(wrapper, defaultAddressProcessor ,$) {
	'use strict';

	return function(placeOrderAction) {
		return wrapper.wrap(placeOrderAction, function(originalAction, paymentData, redirectOnSuccess) {
			if (paymentData && paymentData.method.startsWith('rvvup_')) {
				// Update shipping address data
				defaultAddressProcessor.saveShippingInformation();
				// do not create order for rvvup payments as it will be created later on.
				return;
			}

			return originalAction(paymentData, redirectOnSuccess);
		});
	};
});
