/**
 * Copyright © Perspective. All rights reserved.
 *
 * Luma checkout steps: "Checkout Step Viewed" when a step becomes visible, "Checkout Step Completed" when the
 * customer moves forward from it. Both are disabled by default (UA-style funnel).
 */
define([
    'Magento_Checkout/js/model/step-navigator'
], function (stepNavigator) {
    'use strict';

    var current = null,
        subscribed = {};

    /**
     * 1-based number of a step by sort order
     *
     * @param {String} code
     * @return {Number}
     */
    function stepNumber(code) {
        var steps = stepNavigator.steps().slice().sort(stepNavigator.sortItems),
            i;

        for (i = 0; i < steps.length; i++) {
            if (steps[i].code === code) {
                return i + 1;
            }
        }

        return 0;
    }

    /**
     * @param {String} code
     * @param {Number} step
     */
    function push(code, step) {
        var core = window.PerspectiveZaraz,
            event = core ? core.getTemplate(code) : null;

        if (event && step) {
            event.payload.step = step;
            core.push(event);
        }
    }

    /**
     * @param {Object} step
     */
    function onVisible(step) {
        var number = stepNumber(step.code),
            previous = current ? stepNumber(current) : 0;

        if (step.code === current) {
            return;
        }

        if (previous && number > previous) {
            push('checkout_step_completed', previous);
        }
        current = step.code;
        push('checkout_step_viewed', number);
    }

    /**
     * Subscribe to visibility of registered steps
     */
    function subscribeSteps() {
        stepNavigator.steps().forEach(function (step) {
            if (subscribed[step.code]) {
                return;
            }
            subscribed[step.code] = true;
            step.isVisible.subscribe(function (visible) {
                if (visible) {
                    onVisible(step);
                }
            });

            if (step.isVisible()) {
                onVisible(step);
            }
        });
    }

    return function () {
        subscribeSteps();
        stepNavigator.steps.subscribe(subscribeSteps);
    };
});
