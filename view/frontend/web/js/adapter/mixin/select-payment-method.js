/**
 * Copyright © Perspective. All rights reserved.
 *
 * Luma checkout: "Payment Info Entered" when a payment method is selected (template + payment_type),
 * once per distinct method
 */
define([
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/payment/method-list'
], function (wrapper, methodList) {
    'use strict';

    var lastMethod = null;

    /**
     * @param {String} code
     * @return {String}
     */
    function getTitle(code) {
        var found = (methodList() || []).filter(function (method) {
            return method.method === code;
        })[0];

        return found && found.title ? found.title : code;
    }

    return function (selectPaymentMethod) {
        return wrapper.wrap(selectPaymentMethod, function (original, paymentMethod) {
            var result = original(paymentMethod),
                core = window.PerspectiveZaraz,
                code = paymentMethod && paymentMethod.method,
                event;

            if (code && code !== lastMethod && core) {
                event = core.getTemplate('payment_info_entered');

                if (event) {
                    lastMethod = code;
                    event.payload.payment_type = getTitle(code);
                    core.push(event);
                }
            }

            return result;
        });
    };
});
