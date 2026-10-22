/**
 * Copyright © Perspective. All rights reserved.
 *
 * Luma adapter for the PerspectiveZaraz core:
 * - action events and customer zaraz.set() properties from the "perspective-zaraz" customer-data section;
 * - Product Clicked: clicks on product links of server-rendered lists (data-perspective-zaraz="list");
 * - Viewed Promotion / Clicked Promotion: elements with data-zaraz-promotion-id (-name, -creative, -position).
 * The core does not know about this adapter; other themes use the same public JS contract.
 */
define([
    'jquery',
    'Magento_Customer/js/customer-data'
], function ($, customerData) {
    'use strict';

    var SECTION = 'perspective-zaraz',
        PENDING_COOKIE = 'perspective_zaraz_pending',
        PROMOTION_SELECTOR = '[data-zaraz-promotion-id]',
        lastUser = null;

    /**
     * Push to the core, or to the pre-load queue when the deferred core is not executed yet
     *
     * @param {Object} event
     */
    function push(event) {
        if (window.PerspectiveZaraz) {
            window.PerspectiveZaraz.push(event);
        } else {
            (window.PerspectiveZarazQueue = window.PerspectiveZarazQueue || []).push(event);
        }
    }

    /**
     * Run when the deferred core is available
     *
     * @param {Function} callback
     */
    function withCore(callback) {
        if (window.PerspectiveZaraz) {
            callback(window.PerspectiveZaraz);
        } else if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                return window.PerspectiveZaraz && callback(window.PerspectiveZaraz);
            });
        } else {
            window.setTimeout(function () {
                return window.PerspectiveZaraz && callback(window.PerspectiveZaraz);
            }, 100);
        }
    }

    /**
     * Section data: user properties on every page, action events once
     *
     * @param {Object} data
     */
    function handleSection(data) {
        var events = data && data.events;

        if (!data) {
            return;
        }

        if (data.debug) {
            withCore(function (core) {
                core.setDebug(true);
            });
        }
        // zaraz.set() again only when the properties changed
        if (JSON.stringify(data.user || []) !== lastUser) {
            lastUser = JSON.stringify(data.user || []);
            (data.user || []).forEach(push);
        }

        if (!events || !events.length) {
            return;
        }
        // Read-once on the server; also emptied in localStorage so a reload does not replay it
        customerData.set(SECTION, {
            events: [],
            user: data.user || [],
            debug: !!data.debug
        });
        events.forEach(push);
    }

    /**
     * Product Clicked template of the list rendered right after the clicked element
     *
     * @param {Element} element
     * @param {String} productId
     * @return {Object|null}
     */
    function findClickEvent(element, productId) {
        var lists = document.querySelectorAll('script[type="application/json"][data-perspective-zaraz="list"]'),
            data,
            i;

        for (i = 0; i < lists.length; i++) {
            data = element.compareDocumentPosition(lists[i]) & Node.DOCUMENT_POSITION_FOLLOWING ?
                JSON.parse(lists[i].textContent || '{}') :
                {};

            if (data.clicks && data.clicks[productId]) {
                return data.clicks[productId];
            }
        }

        return null;
    }

    /**
     * @param {Event} domEvent
     */
    function onProductClick(domEvent) {
        var link = domEvent.target.closest ? domEvent.target.closest('a') : null,
            item = link ? link.closest('.product-item') : null,
            idElement = item ? item.querySelector('[data-product-id]') : null,
            event;

        if (!idElement) {
            return;
        }
        event = findClickEvent(item, idElement.getAttribute('data-product-id'));

        if (event) {
            push(event);
        }
    }

    /**
     * Promotion template filled from data attributes
     *
     * @param {String} code
     * @param {Element} element
     * @return {Object|null}
     */
    function promotionEvent(code, element) {
        var event = window.PerspectiveZaraz ? window.PerspectiveZaraz.getTemplate(code) : null,
            position = element.getAttribute('data-zaraz-promotion-position');

        if (!event) {
            return null;
        }
        event.payload.promotion_id = element.getAttribute('data-zaraz-promotion-id');
        event.payload.name = element.getAttribute('data-zaraz-promotion-name') || event.payload.promotion_id;

        if (element.getAttribute('data-zaraz-promotion-creative')) {
            event.payload.creative = element.getAttribute('data-zaraz-promotion-creative');
        }

        if (position !== null && position !== '') {
            event.payload.position = isNaN(Number(position)) ? position : Number(position);
        }

        return event;
    }

    /**
     * Viewed Promotion once per element when at least half of it is visible
     */
    function observePromotions() {
        var elements = document.querySelectorAll(PROMOTION_SELECTOR),
            observer;

        if (!elements.length || !window.IntersectionObserver) {
            return;
        }
        observer = new window.IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                var event;

                if (!entry.isIntersecting) {
                    return;
                }
                observer.unobserve(entry.target);
                event = promotionEvent('viewed_promotion', entry.target);

                if (event) {
                    push(event);
                }
            });
        }, {
            threshold: 0.5
        });
        Array.prototype.forEach.call(elements, function (element) {
            observer.observe(element);
        });
    }

    /**
     * @param {Event} domEvent
     */
    function onPromotionClick(domEvent) {
        var element = domEvent.target.closest ? domEvent.target.closest(PROMOTION_SELECTOR) : null,
            event = element ? promotionEvent('clicked_promotion', element) : null;

        if (event) {
            push(event);
        }
    }

    return function () {
        var section = customerData.get(SECTION);

        handleSection(section());
        section.subscribe(handleSection);

        // Also read the section straight from section/load responses: an exception in another section subscriber
        // (third-party code) stops customer-data from notifying the remaining sections of the same response
        $(document).on('ajaxComplete', function (event, xhr, settings) {
            var data = settings && settings.url && settings.url.indexOf('customer/section/load') !== -1 &&
                xhr.responseJSON ? xhr.responseJSON[SECTION] : null;

            if (data) {
                handleSection(data);
            }
        });

        // Events queued by an action that did not invalidate the section in the browser (native submit, redirect)
        if (document.cookie.split('; ').indexOf(PENDING_COOKIE + '=1') !== -1) {
            customerData.reload([SECTION], false);
        }

        // Capture phase: runs before navigation handlers of the theme
        document.addEventListener('click', onProductClick, true);
        document.addEventListener('click', onPromotionClick, true);
        withCore(observePromotions);
    };
});
