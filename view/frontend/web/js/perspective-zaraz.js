/**
 * Copyright © Perspective. All rights reserved.
 *
 * PerspectiveZaraz core: framework-free (ES5, no RequireJS/jQuery/KnockoutJS) bridge to the Cloudflare Zaraz
 * Web API. Works with any frontend (Luma, Hyvä, Breeze, PWA, headless).
 *
 * Public contract (see README "JS contract"):
 *   PerspectiveZaraz.push({method: 'ecommerce'|'track'|'set', name: 'Product Added', payload: {...}, id: 'x'})
 *   - ecommerce -> zaraz.ecommerce(name, payload)
 *   - track     -> zaraz.track(name, payload)
 *   - set       -> zaraz.set(name, payload.value, {scope: payload.scope}); value null unsets the key
 *   - id        -> optional; the event is sent at most once per browser (localStorage)
 *   - code      -> optional module event code ("product_clicked", "product_added", ...) used for list attribution
 * Before this file is executed events can be queued:
 *   (window.PerspectiveZarazQueue = window.PerspectiveZarazQueue || []).push(event)
 * Server-rendered events: <script type="application/json" data-perspective-zaraz="events">[...]</script>
 * Templates (sent by an adapter on a browser action, e.g. after the shipping step):
 *   <script type="application/json" data-perspective-zaraz="templates">{"shipping_info_entered": event}</script>
 *   PerspectiveZaraz.getTemplate('shipping_info_entered') returns a copy or null (disabled event)
 * Config: <script type="application/json" id="perspective-zaraz-config">{"debug": true}</script>
 * Delivery: PerspectiveZaraz.onDelivered(function (event) {...}) is called for every event Zaraz accepted
 *   (the zaraz.* promise resolved), including events delivered before the subscription
 */
(function (window, document) {
    'use strict';

    var VERSION = '0.2.0',
        SENT_KEY = 'perspective_zaraz_sent',
        LISTS_KEY = 'perspective_zaraz_lists',
        LISTS_LIMIT = 200,
        // Events whose products get the list they were clicked in
        ATTRIBUTED = {
            product_viewed: true,
            product_added: true,
            product_removed: true,
            cart_viewed: true,
            checkout_started: true,
            shipping_info_entered: true,
            payment_info_entered: true,
            order_completed: true
        },
        SENT_TTL = 30 * 24 * 3600 * 1000,
        SENT_LIMIT = 200,
        HISTORY_LIMIT = 100,
        POLL_INTERVAL = 200,
        POLL_TIMEOUT = 10000,
        METHODS = {
            ecommerce: true,
            track: true,
            set: true
        },
        SET_SCOPES = {
            page: true,
            session: true,
            persist: true
        },
        // https://developers.cloudflare.com/zaraz/web-api/ecommerce/
        ECOMMERCE_EVENTS = [
            'Product List Viewed', 'Products Searched', 'Product Clicked', 'Product Added',
            'Product Added to Wishlist', 'Product Removed', 'Product Viewed', 'Cart Viewed', 'Checkout Started',
            'Checkout Step Viewed', 'Checkout Step Completed', 'Payment Info Entered', 'Order Completed',
            'Order Updated', 'Order Refunded', 'Order Cancelled', 'Clicked Promotion', 'Viewed Promotion',
            'Shipping Info Entered'
        ],
        STRING_PARAMS = [
            'product_id', 'sku', 'category', 'name', 'brand', 'variant', 'coupon', 'checkout_id', 'order_id',
            'affiliation', 'currency', 'creative', 'query', 'payment_type'
        ],
        NUMBER_PARAMS = [
            'price', 'quantity', 'position', 'total', 'revenue', 'shipping', 'tax', 'discount', 'value', 'step'
        ],
        debug = false,
        attribution = {
            enabled: false,
            ttl: 1440
        },
        templates = {},
        pending = [],
        history = [],
        delivered = [],
        deliveryListeners = [],
        timer = null,
        waited = 0,
        warnedMissing = false,
        flush,
        schedule;

    if (window.PerspectiveZaraz && window.PerspectiveZaraz.version) {
        return;
    }

    /**
     * @param {String} level
     * @param {String} message
     * @param {*} [data]
     */
    function log(level, message, data) {
        var console = window.console;

        if (!debug || !console) {
            return;
        }
        (console[level] || console.log).call(
            console,
            '%c[PerspectiveZaraz]%c ' + message,
            'color:#f38020;font-weight:bold',
            '',
            data === undefined ? '' : data
        );
    }

    /**
     * @param {String} json
     * @param {*} fallback
     * @return {*}
     */
    function parseJson(json, fallback) {
        try {
            return JSON.parse(json) || fallback;
        } catch (error) {
            log('warn', 'invalid JSON', error);

            return fallback;
        }
    }

    /**
     * @param {*} value
     * @param {Array} list
     * @return {Boolean}
     */
    function inArray(value, list) {
        var i;

        for (i = 0; i < list.length; i++) {
            if (list[i] === value) {
                return true;
            }
        }

        return false;
    }

    /* ---- at-most-once delivery ------------------------------------------------------------------------- */

    /**
     * @return {Object} id => timestamp
     */
    function readSent() {
        try {
            return parseJson(window.localStorage.getItem(SENT_KEY) || '{}', {});
        } catch (error) {
            log('warn', 'localStorage is not available', error);

            return {};
        }
    }

    /**
     * Keep only recent ids
     *
     * @param {Object} sent
     * @return {Object}
     */
    function prune(sent) {
        var now = new Date().getTime(),
            ids = [],
            kept = {},
            id,
            i;

        for (id in sent) {
            if (sent.hasOwnProperty(id) && now - sent[id] < SENT_TTL) {
                ids.push(id);
            }
        }
        ids.sort(function (a, b) {
            return sent[b] - sent[a];
        });

        for (i = 0; i < ids.length && i < SENT_LIMIT; i++) {
            kept[ids[i]] = sent[ids[i]];
        }

        return kept;
    }

    /**
     * Mark an id as sent; false when it was already sent
     *
     * @param {String} id
     * @return {Boolean}
     */
    function claim(id) {
        var sent = readSent();

        if (sent[id]) {
            return false;
        }
        sent[id] = new Date().getTime();

        try {
            window.localStorage.setItem(SENT_KEY, JSON.stringify(prune(sent)));
        } catch (error) {
            // Private mode or quota: duplicates are then prevented only server-side
            log('warn', 'cannot store sent event ids', error);
        }

        return true;
    }

    /* ---- validation against the Zaraz spec -------------------------------------------------------------- */

    /**
     * @param {Object} event
     * @return {String} error or empty string
     */
    function validate(event) {
        if (!event || typeof event !== 'object') {
            return 'event must be an object';
        }

        if (!METHODS[event.method]) {
            return 'unknown method "' + event.method + '"';
        }

        if (typeof event.name !== 'string' || event.name === '') {
            return 'name must be a non-empty string';
        }

        if (event.payload !== undefined && (typeof event.payload !== 'object' || event.payload === null)) {
            return 'payload must be an object';
        }

        if (event.method === 'set' && event.payload && event.payload.scope && !SET_SCOPES[event.payload.scope]) {
            return 'set scope must be page, session or persist';
        }

        return '';
    }

    /**
     * @param {Object} params
     * @param {String} path
     * @param {Array} warnings
     */
    function checkParams(params, path, warnings) {
        var key;

        for (key in params) {
            if (!params.hasOwnProperty(key)) {
                continue;
            }

            if (inArray(key, STRING_PARAMS) && typeof params[key] !== 'string') {
                warnings.push(path + key + ' should be a string');
            } else if (inArray(key, NUMBER_PARAMS) && typeof params[key] !== 'number') {
                warnings.push(path + key + ' should be a number');
            }
        }
    }

    /**
     * @param {*} products
     * @param {Array} warnings
     */
    function checkProducts(products, warnings) {
        var i;

        if (Object.prototype.toString.call(products) !== '[object Array]') {
            warnings.push('products should be an array');

            return;
        }

        for (i = 0; i < products.length; i++) {
            checkParams(products[i] || {}, 'products[' + i + '].', warnings);
        }
    }

    /**
     * Warnings about names and types not matching the Zaraz e-commerce spec
     *
     * @param {Object} event
     * @return {Array}
     */
    function specWarnings(event) {
        var warnings = [],
            payload = event.payload || {};

        if (event.method !== 'ecommerce') {
            return warnings;
        }

        if (!inArray(event.name, ECOMMERCE_EVENTS)) {
            warnings.push('"' + event.name + '" is not an event of the Zaraz e-commerce spec');
        }
        checkParams(payload, '', warnings);

        if (payload.products !== undefined) {
            checkProducts(payload.products, warnings);
        }

        return warnings;
    }

    /* ---- delivery to Zaraz ------------------------------------------------------------------------------ */

    /**
     * @param {String} method
     * @return {Boolean}
     */
    function isReady(method) {
        var zaraz = window.zaraz;

        return !!zaraz && typeof zaraz[method] === 'function';
    }

    /**
     * @param {Object} event accepted by Zaraz
     */
    function notifyDelivered(event) {
        var i;

        delivered.push(event);

        if (delivered.length > HISTORY_LIMIT) {
            delivered.shift();
        }

        for (i = 0; i < deliveryListeners.length; i++) {
            try {
                deliveryListeners[i](event);
            } catch (error) {
                log('error', 'onDelivered listener failed', error);
            }
        }
    }

    /**
     * @param {Object} event
     */
    function call(event) {
        var zaraz = window.zaraz,
            payload = event.payload || {},
            value,
            result;

        if (event.method === 'set') {
            // null comes from JSON for "unset"
            value = payload.value === null ? undefined : payload.value;
            result = payload.scope ?
                zaraz.set(event.name, value, {
                    scope: payload.scope
                }) :
                zaraz.set(event.name, value);
        } else {
            result = zaraz[event.method](event.name, payload);
        }

        if (result && typeof result.then === 'function') {
            result.then(function () {
                notifyDelivered(event);
            }, function (error) {
                log('error', 'zaraz.' + event.method + '("' + event.name + '") rejected', error);
            });
        } else {
            notifyDelivered(event);
        }
    }

    /**
     * Send every pending event whose Zaraz method is available
     */
    flush = function () {
        var rest = [],
            i,
            event;

        for (i = 0; i < pending.length; i++) {
            event = pending[i];

            if (!isReady(event.method)) {
                rest.push(event);
                continue;
            }

            try {
                call(event);
                log('log', 'sent to zaraz.' + event.method + ': ' + event.name);
            } catch (error) {
                log('error', 'zaraz.' + event.method + '("' + event.name + '") failed', error);
            }
        }
        pending = rest;
        schedule();
    };

    /**
     * Poll for Zaraz while events are pending, up to POLL_TIMEOUT (re-armed on window load)
     */
    schedule = function () {
        if (!pending.length || timer) {
            return;
        }

        if (waited >= POLL_TIMEOUT) {
            if (!warnedMissing) {
                warnedMissing = true;
                log(
                    'warn',
                    'Zaraz is not available (no Cloudflare proxy, auto-inject off or e-commerce tracking disabled ' +
                    'in the Zaraz dashboard); ' + pending.length + ' event(s) kept in queue',
                    pending
                );
            }

            return;
        }
        timer = window.setTimeout(function () {
            timer = null;
            waited += POLL_INTERVAL;
            flush();
        }, POLL_INTERVAL);
    };

    /* ---- list attribution ------------------------------------------------------------------------------ */

    /**
     * @return {Object} product_id => {list_id, list_name, position, ts}
     */
    function readLists() {
        try {
            return parseJson(window.localStorage.getItem(LISTS_KEY) || '{}', {});
        } catch (error) {
            log('warn', 'localStorage is not available', error);

            return {};
        }
    }

    /**
     * Remember the list a product was clicked in
     *
     * @param {Object} payload
     */
    function rememberList(payload) {
        var lists = readLists(),
            ids = [],
            kept = {},
            id,
            i;

        if (!payload.product_id || !payload.list_name && !payload.list_id) {
            return;
        }
        lists[payload.product_id] = {
            list_id: payload.list_id,
            list_name: payload.list_name,
            position: payload.position,
            ts: new Date().getTime()
        };

        for (id in lists) {
            if (lists.hasOwnProperty(id)) {
                ids.push(id);
            }
        }
        ids.sort(function (a, b) {
            return lists[b].ts - lists[a].ts;
        });

        for (i = 0; i < ids.length && i < LISTS_LIMIT; i++) {
            kept[ids[i]] = lists[ids[i]];
        }

        try {
            window.localStorage.setItem(LISTS_KEY, JSON.stringify(kept));
        } catch (error) {
            log('warn', 'cannot store list attribution', error);
        }
    }

    /**
     * Copy list fields to a product that has none
     *
     * @param {Object} product
     * @param {Object} lists
     * @param {Boolean} withListId
     */
    function applyList(product, lists, withListId) {
        var list = product && product.product_id ? lists[product.product_id] : null;

        if (!list || product.list_name || new Date().getTime() - list.ts > attribution.ttl * 60000) {
            return;
        }

        if (list.list_name) {
            product.list_name = list.list_name;
        }

        if (list.position && product.position === undefined) {
            product.position = list.position;
        }

        if (withListId && list.list_id && product.list_id === undefined) {
            product.list_id = list.list_id;
        }
    }

    /**
     * Add remembered lists to products of cart/checkout/order events
     *
     * @param {Object} event
     */
    function attribute(event) {
        var payload = event.payload || {},
            lists,
            i;

        if (!attribution.enabled || !event.code) {
            return;
        }

        if (event.code === 'product_clicked') {
            rememberList(payload);

            return;
        }

        if (!ATTRIBUTED[event.code]) {
            return;
        }
        lists = readLists();
        applyList(payload, lists, true);

        if (Object.prototype.toString.call(payload.products) === '[object Array]') {
            for (i = 0; i < payload.products.length; i++) {
                applyList(payload.products[i], lists, false);
            }
        }
    }

    /* ---- public API ------------------------------------------------------------------------------------- */

    /**
     * @param {Object} event
     * @return {Boolean} false when the event is invalid or was already sent
     */
    function push(event) {
        var error = validate(event),
            warnings;

        if (error) {
            log('error', 'invalid event: ' + error, event);

            return false;
        }

        if (event.id && !claim(String(event.id))) {
            log('log', 'skipped, already sent: ' + event.id);

            return false;
        }
        attribute(event);
        history.push(event);

        if (history.length > HISTORY_LIMIT) {
            history.shift();
        }
        warnings = specWarnings(event);
        log(warnings.length ? 'warn' : 'log', event.method + ' "' + event.name + '"', event.payload || {});

        if (warnings.length) {
            log('warn', 'spec warnings for "' + event.name + '"', warnings);
        }
        pending.push(event);
        flush();

        return true;
    }

    /**
     * @param {Array} events
     */
    function pushAll(events) {
        var i;

        for (i = 0; events && i < events.length; i++) {
            push(events[i]);
        }
    }

    /**
     * @param {Object} data code => event
     */
    function addTemplates(data) {
        var code;

        for (code in data) {
            if (data.hasOwnProperty(code)) {
                templates[code] = data[code];
            }
        }
    }

    /**
     * Push server-rendered events that were not processed yet
     *
     * @param {Element|Document} [root]
     */
    function scan(root) {
        var nodes = (root || document).querySelectorAll(
                'script[type="application/json"][data-perspective-zaraz]'
            ),
            type,
            i;

        for (i = 0; i < nodes.length; i++) {
            type = nodes[i].getAttribute('data-perspective-zaraz');

            if (nodes[i].getAttribute('data-perspective-zaraz-done') || type === 'list') {
                continue;
            }
            nodes[i].setAttribute('data-perspective-zaraz-done', '1');

            if (type === 'events') {
                pushAll(parseJson(nodes[i].textContent || '[]', []));
            } else if (type === 'templates') {
                addTemplates(parseJson(nodes[i].textContent || '{}', {}));
            }
        }
    }

    /**
     * Copy of a template event, or null when the event is disabled / not on this page
     *
     * @param {String} code
     * @return {Object|null}
     */
    function getTemplate(code) {
        return templates[code] ? parseJson(JSON.stringify(templates[code]), null) : null;
    }

    /**
     * @param {Boolean} flag
     */
    function setDebug(flag) {
        var i;

        if (debug === !!flag) {
            return;
        }
        debug = !!flag;

        if (debug && history.length) {
            log('log', 'debug enabled; events pushed before:');

            for (i = 0; i < history.length; i++) {
                log('log', history[i].method + ' "' + history[i].name + '"', history[i].payload || {});
            }
        }
    }

    /**
     * @return {Object}
     */
    function readConfig() {
        var element = document.getElementById('perspective-zaraz-config');

        return element ? parseJson(element.textContent || '{}', {}) : {};
    }

    (function (config) {
        debug = !!config.debug;

        if (config.attribution) {
            attribution.enabled = !!config.attribution.enabled;
            attribution.ttl = config.attribution.ttl || attribution.ttl;
        }
    }(readConfig()));

    window.PerspectiveZaraz = {
        version: VERSION,
        push: push,
        scan: scan,
        getTemplate: getTemplate,
        setDebug: setDebug,

        /** @return {Boolean} */
        isDebug: function () {
            return debug;
        },

        /** @return {Array} events pushed on this page */
        getHistory: function () {
            return history.slice();
        },

        /** @return {Array} events waiting for Zaraz */
        getPending: function () {
            return pending.slice();
        },

        /**
         * Subscribe to events accepted by Zaraz; events delivered earlier on this page are passed at once
         *
         * @param {Function} callback
         */
        onDelivered: function (callback) {
            var i;

            if (typeof callback !== 'function') {
                return;
            }

            for (i = 0; i < delivered.length; i++) {
                callback(delivered[i]);
            }
            deliveryListeners.push(callback);
        }
    };

    // Events queued before the core was executed; later pushes to the queue go straight to the core
    pushAll(window.PerspectiveZarazQueue);
    window.PerspectiveZarazQueue = {
        push: push
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            scan();
        });
    } else {
        scan();
    }
    window.addEventListener('load', function () {
        waited = 0;
        warnedMissing = false;
        flush();
    });
}(window, document));
