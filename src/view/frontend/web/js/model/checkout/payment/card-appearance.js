/**
 * Appearance configuration for the inline card fields rendered by the Rvvup JS SDK.
 *
 * Themes and merchant customisations can adjust the styling the Magento native way,
 * either with a RequireJS mixin:
 *
 *   var config = {
 *       config: {
 *           mixins: {
 *               'Rvvup_Payments/js/model/checkout/payment/card-appearance': {
 *                   'My_Theme/js/card-appearance-mixin': true
 *               }
 *           }
 *       }
 *   };
 *
 * where the mixin wraps the returned function and changes the configuration:
 *
 *   define([], function () {
 *       'use strict';
 *
 *       return function (originalCardAppearance) {
 *           return function () {
 *               var appearance = originalCardAppearance();
 *               appearance.input.base.borderColor = '#ff5501';
 *               return appearance;
 *           };
 *       };
 *   });
 *
 * or by mapping the module to a replacement via requirejs-config.js.
 *
 * Supported options are documented at https://merchantdocs.zopa.com/ui/v2/inline/Card
 */
define([], function () {
    'use strict';

    return function () {
        return {
            input: {
                base: {
                    borderStyle: 'solid',
                    borderWidth: '1px',
                    borderColor: '#c2c2c2',
                    borderRadius: '3px',
                    color: '#333333',
                    fontSize: '14px',
                    padding: '8px 12px',
                    lineHeight: '20px'
                }
            }
        };
    };
});
