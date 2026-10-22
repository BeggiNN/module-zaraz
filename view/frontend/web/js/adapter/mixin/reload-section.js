/**
 * Copyright © Perspective. All rights reserved.
 *
 * Luma checkout coupon actions use REST: reload the "perspective-zaraz" section afterwards to deliver
 * Coupon Applied / Coupon Removed queued on the server
 */
define([
    'mage/utils/wrapper',
    'Magento_Customer/js/customer-data'
], function (wrapper, customerData) {
    'use strict';

    return function (action) {
        return wrapper.wrap(action, function (original) {
            var result = original.apply(null, Array.prototype.slice.call(arguments, 1));

            if (window.PerspectiveZaraz && result && typeof result.done === 'function') {
                result.done(function () {
                    customerData.reload(['perspective-zaraz'], false);
                });
            }

            return result;
        });
    };
});
