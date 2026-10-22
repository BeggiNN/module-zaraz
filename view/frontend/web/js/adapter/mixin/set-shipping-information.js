/**
 * Copyright © Perspective. All rights reserved.
 *
 * Luma checkout: "Shipping Info Entered" after the shipping step is saved (template + shipping_tier)
 */
define([
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote'
], function (wrapper, quote) {
    'use strict';

    return function (setShippingInformation) {
        return wrapper.wrap(setShippingInformation, function (original) {
            return original().done(function () {
                var core = window.PerspectiveZaraz,
                    event = core ? core.getTemplate('shipping_info_entered') : null,
                    method = quote.shippingMethod();

                if (!event) {
                    return;
                }

                if (method) {
                    event.payload.shipping_tier = [method.carrier_title, method.method_title]
                        .filter(Boolean)
                        .join(' - ');
                }
                core.push(event);
            });
        });
    };
});
