/**
 * Copyright © Perspective. All rights reserved.
 */
var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/action/set-shipping-information': {
                'Perspective_Zaraz/js/adapter/mixin/set-shipping-information': true
            },
            'Magento_Checkout/js/action/select-payment-method': {
                'Perspective_Zaraz/js/adapter/mixin/select-payment-method': true
            },
            'Magento_SalesRule/js/action/set-coupon-code': {
                'Perspective_Zaraz/js/adapter/mixin/reload-section': true
            },
            'Magento_SalesRule/js/action/cancel-coupon': {
                'Perspective_Zaraz/js/adapter/mixin/reload-section': true
            }
        }
    }
};
