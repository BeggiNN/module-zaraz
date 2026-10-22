/**
 * Copyright © Perspective. All rights reserved.
 *
 * Preview Payload page: ID placeholder per entity type, copy JSON to clipboard
 */
define([], function () {
    'use strict';

    return function (config, root) {
        var input = root.querySelector('input[name="entity"]');

        Array.prototype.forEach.call(root.querySelectorAll('input[name="type"]'), function (radio) {
            radio.addEventListener('change', function () {
                input.placeholder = radio.getAttribute('data-placeholder');
                input.focus();
            });
        });

        Array.prototype.forEach.call(root.querySelectorAll('.pz-copy'), function (button) {
            button.addEventListener('click', function (event) {
                var label = button.textContent;

                // Inside <summary>: do not toggle the details element
                event.preventDefault();

                if (!navigator.clipboard) {
                    return;
                }
                navigator.clipboard.writeText(button.getAttribute('data-json')).then(function () {
                    button.textContent = button.getAttribute('data-done');
                    window.setTimeout(function () {
                        button.textContent = label;
                    }, 1500);
                });
            });
        });
    };
});
