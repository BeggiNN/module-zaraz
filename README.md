# Perspective_Zaraz

E-commerce data layer for [Cloudflare Zaraz](https://developers.cloudflare.com/zaraz/) in Magento 2.

The module turns storefront activity into Zaraz calls:

- `zaraz.ecommerce()` — events from the [Zaraz E-commerce spec](https://developers.cloudflare.com/zaraz/web-api/ecommerce/)
  (Product Viewed, Product Added, Order Completed…);
- `zaraz.track()` — custom events (compare, coupons, login, sign-up);
- `zaraz.set()` — customer and page properties.

It does **not** know about pixel IDs or vendor tags. Where the data goes (GA4, Google Ads, Meta, TikTok, Pinterest,
Bing, Clarity…) is configured with tools in the Zaraz dashboard, and Zaraz maps the standard e-commerce events to each
vendor's format. This replaces the usual Google Tag Manager + data layer extension setup: no GTM container, no tags in
the theme, and vendor requests are sent from Cloudflare's edge instead of the visitor's browser.

| | |
|---|---|
| Composer package | `perspectiveteam/module-zaraz` |
| Magento module | `Perspective_Zaraz` |
| Config path | `perspective_zaraz/*` |
| Requirements | Magento Open Source / Adobe Commerce 2.4.8, PHP 8.2–8.4; MSI is optional |
| Server-side events | separate package [`perspectiveteam/module-zaraz-server-side`](https://github.com/PerspectiveTeam/module-zaraz-server-side) |

## Installation

```bash
composer require perspectiveteam/module-zaraz
bin/magento module:enable Perspective_Zaraz
bin/magento setup:upgrade
bin/magento setup:di:compile             # production mode
bin/magento setup:static-content:deploy  # production mode
```

For local development the package can be linked as a path repository:

```json
"repositories": {
    "perspective": { "type": "path", "url": "packages/perspective/*", "options": { "symlink": true } }
}
```

## Cloudflare setup

1. The store domain must be proxied by Cloudflare (orange cloud DNS record or Cloudflare Tunnel). Zaraz only works on
   proxied hostnames.
2. **Zaraz → Tag setup → Settings**: enable **E-commerce tracking**, **Auto-inject script** and
   **Automatic Pageview Tracking**. Without Auto-inject, Cloudflare does not add the Zaraz script to the page; in that
   case set **Script mode = Manual** in the module and the module will render the script tag itself.
3. Add the tools you need (GA4, Meta Pixel…) and enable their **Automatic actions**: Pageviews, Events and
   E-commerce. E-commerce events reach a tool only when its E-commerce action is on.
4. Check on a storefront page: `typeof zaraz === 'object'` in the browser console. With the module's Debug Mode on,
   the console also lists every event that was sent.

Exclude the Magento admin from Zaraz: **Zaraz → Rules → Configuration Rules**, condition
`Hostname equals <store host>` **And** `URI Path starts with /<admin path>`, action **Disable Zaraz**.

## Events

| Code (row in "Events") | Zaraz event | Method | When it fires |
|---|---|---|---|
| `product_list_viewed` | Product List Viewed | ecommerce | category, search results, related / upsell / cross-sell, CatalogWidget; sent in chunks of `List Chunk Size` |
| `products_searched` | Products Searched | ecommerce | search results page |
| `product_clicked` | Product Clicked | ecommerce | click on a product in a list |
| `product_viewed` | Product Viewed | ecommerce | product page |
| `product_added` / `product_removed` | Product Added / Product Removed | ecommerce | any cart quantity change: product page, list, minicart, cart update, cart merge on login |
| `product_added_to_wishlist` | Product Added to Wishlist | ecommerce | wishlist |
| `product_added_to_compare` | Product Added to Compare | track | compare list |
| `cart_viewed` | Cart Viewed | ecommerce | cart page |
| `checkout_started` | Checkout Started | ecommerce | checkout page |
| `checkout_step_viewed` / `checkout_step_completed` | Checkout Step Viewed / Completed | ecommerce | checkout steps (disabled by default) |
| `shipping_info_entered` | Shipping Info Entered | ecommerce | after the shipping step, with `shipping_tier` |
| `payment_info_entered` | Payment Info Entered | ecommerce | payment method selected, with `payment_type` |
| `order_completed` | Order Completed | ecommerce | success page, once per order |
| `viewed_promotion` / `clicked_promotion` | Viewed / Clicked Promotion | ecommerce | elements with `data-zaraz-promotion-*` attributes |
| `coupon_applied` / `coupon_removed` | Coupon Applied / Coupon Removed | track | coupon code applied or removed |
| `login` / `sign_up` | login / sign_up | track | customer login / registration |

The **Events** table in the configuration lets you, per event:

- **disable** it;
- **rename** it — useful only for custom triggers. Zaraz e-commerce tools recognise the spec names only, so a renamed
  e-commerce event will not be mapped to GA4 `purchase`, Meta `Purchase`, etc.;
- choose the channel: **From browser** and/or **From server** (the server channel needs the server-side package).

An event without a row in the table is sent from the browser under its default name.

### What vendors actually receive

Zaraz maps the spec events itself, and each tool has quirks. The ones that matter for configuration:

- **GA4**: `sku` takes priority over `product_id` as `item_id`; only one category reaches GA4; zero values (such as
  `shipping: 0`) are dropped; custom product properties reach GA4 only at event level, not inside items.
- **Meta**: `sku` takes priority over `product_id` as the content id. Purchase deduplication between the browser and
  the Conversions API uses `event_id`, which the module sets to `order-<increment_id>`.
- **TikTok**: Order Completed becomes `CompletePayment`; an event without a positive value is dropped; `event_id`
  and `order_id` are not passed through by the Zaraz tool.
- **Pinterest / Bing**: Product Added and Order Completed map to the standard add-to-cart and checkout / purchase
  events; other events arrive as custom events under their Zaraz names.

The **Preview Payload** page (below) shows how each tool will name the generated events.

## Configuration

**Stores → Configuration → Perspective Extensions → Cloudflare Zaraz**. Every setting can be changed per store view,
and every field in the admin has a comment with an example of how it changes the payload. Defaults are chosen so that
the module works with GA4 / Meta / TikTok out of the box: enable it, enable E-commerce tracking in Zaraz, done.

Tool IDs, conversion labels and consent are **not** configured here — they belong to the tools in the Zaraz dashboard.

### General

| Field | Default | Description |
|---|---|---|
| Enable | Yes | No: no scripts are printed, no events are queued, no data is loaded. |
| Debug Mode | No | Logs every event (method, name, payload) and Zaraz spec warnings to the browser console. Works without Cloudflare: when Zaraz is not loaded, events wait in `PerspectiveZaraz.getPending()`. Does not change what is sent. |
| Debug Only for IPs | — | Comma-separated IPs; empty = debug for every visitor. With IPs the flag is delivered per visitor through customer-data, so it is never cached in FPC / Varnish. Behind a proxy, Magento must see the client IP (e.g. `CF-Connecting-IP`). |
| Zaraz Script | Auto-inject | **Auto-inject**: Cloudflare inserts the Zaraz script (normal for proxied domains). **Manual**: the module prints `<script src="…" referrerpolicy="origin">` in `<head>`; use it when Auto-inject is off in Zaraz settings or the domain is not proxied. |
| Zaraz Script URL | `/cdn-cgi/zaraz/i.js` | Manual mode only. The default path, a custom endpoint from Zaraz settings, or a full URL on a proxied subdomain (`https://proxied.example.com/cdn-cgi/zaraz/i.js`); its host is added to CSP `script-src` / `connect-src`. |
| Script Tag Attributes | `data-cfasync="false"` | Added to the module script and the manual Zaraz script. The default stops Rocket Loader from delaying them. `on*`, `src`, `type`, `id` and `nonce` are ignored. |
| Product List Viewed Chunk Size | 20 | Products per Product List Viewed event: a list of 36 products with size 20 sends 2 events. Smaller chunks keep payloads under tool limits, but each call is a billable Zaraz event. |
| Custom Checkout Pages | — | One per line: a full action name (`onestepcheckout_index_index`) or a path prefix (`onestepcheckout`). These pages get Checkout Started and the checkout templates. The native checkout is always covered. Needed for one-step checkout extensions. |
| Custom Success Pages | — | Same format. These pages render Order Completed for the last order of the checkout session. The native success page is always covered. |

### Events

A table with one row per event (see [Events](#events) for the list):

| Column | Description |
|---|---|
| Event | Module event code. |
| Enabled | Off: the event is not generated at all. Checkout Step Viewed / Completed are off by default (a UA-style funnel that GA4 does not need). |
| Zaraz Event Name | Overrides the name passed to Zaraz. Zaraz e-commerce tools only recognise the spec names, so override only for events used by custom triggers. |
| From browser / From server | Delivery channel. *From server* needs `Perspective_ZarazServerSide`: for Order Completed (on by default) it is a fallback sent only when the browser did not deliver the purchase. |

An event without a row is sent from the browser under its default name. Order Refunded and Order Cancelled exist only
on the server.

### Product Identification

| Field | Default | Description |
|---|---|---|
| Product ID | SKU | Value of `product_id`: `"MH01"` (SKU) or `"1284"` (entity id). Match it to the id used in your Google Merchant / Meta catalog feed, otherwise dynamic remarketing will not find the products. |
| Send "sku" Property | Yes | Adds `sku`. Zaraz GA4 and Meta use `sku` as the item id when it is present, so with Product ID = entity id set this to No, or the entity id will not reach those tools. |
| Configurable Products | Parent | Cart and order lines of configurables: id / sku / name of the parent (`MH01`) or of the selected child (`MH01-XS-Black`). Category always comes from the parent. Product Viewed always uses the parent, because no child is selected yet. |
| Product Viewed: Send All Children | No | Product Viewed also gets `products` with every salable child (variant, child price); Zaraz GA4 sends them as items of `view_item`. Useful when the catalog feed is by variants. |
| Send Variant | Yes | Adds `variant` to configurable lines with a selected child. |
| Variant Source | Option values | `variant` format: `"Black / XS"` (option values), `"MH01-XS-Black"` (child SKU) or the child product name. |
| Send Brand | No | Adds `brand` — the store-level label of the attribute below. |
| Brand Attribute | `manufacturer` | Attribute with the brand, taken from the parent product. |

### Prices and Totals

| Field | Default | Description |
|---|---|---|
| Currency | Display | `currency` and all amounts: the currency the customer sees / the order was placed in, or the website base currency. Use base currency when GA4 reporting must not depend on currency switching. |
| Product Prices | Excluding tax | `price` (and `value` of product events) with or without tax. |
| Order Total | Grand total | `total` of Order Completed — GA4 uses it as the purchase value. **Grand total**: everything the customer paid, adjusted by the exclusions below. **Subtotal**: items after discount (+ item tax unless tax is excluded), never shipping. `revenue` is always the subtotal after discount. |
| Exclude Tax from Total | No | `total` without any tax (items and shipping). |
| Exclude Shipping from Total | No | Grand total only: `total` without shipping. |
| Exclude Shipping Tax from Total | No | Grand total, tax not excluded: `total` without the tax charged on shipping. |
| Shipping Including Tax | No | The `shipping` property includes shipping tax. Does not change `total`. |

Example: an order with items $90 after discount, item tax $9, shipping $5 — Grand total gives `total: 104`; with
"Exclude Tax" `total: 95`; Subtotal gives `total: 99` (or `90` with "Exclude Tax"); `revenue` is `90` in every case.

### Orders

| Field | Default | Description |
|---|---|---|
| Skip Zero Total Orders | Yes | Order Completed is not sent when the grand total is 0 (free orders, 100% discounts), so they do not distort conversion value. |
| Skip Orders with Statuses | — | Order Completed is not sent when the order has one of these statuses right after placing (e.g. `fraud`, `payment_review`). |
| Affiliation | — | `affiliation` of Order Completed. Empty = the store name (General → Store Information → Store Name, or the store group name). |

### Categories

| Field | Default | Description |
|---|---|---|
| Excluded Categories | — | Removed from category paths, e.g. "Default Category", "Sale", "New" — technical or promo categories that should not appear in reports. |
| Use Next Available Category | Yes | When the deepest category of a product is excluded, use the next deepest one. No: such products get no category. |
| Depth Limit | 0 | Maximum number of levels, 0 = all. With 2, `Men/Tops/Jackets` becomes `Men/Tops` or `Tops/Jackets` (see Keep Levels). |
| Keep Levels | From the root | Which levels remain when the depth is limited: from the root or from the product's own category. |
| Format | Path | **Path**: `category: "Men/Tops/Jackets"`. **Levels**: `category: "Men"`, `category2: "Tops"`, `category3: "Jackets"` (up to 5). Zaraz GA4 forwards only `category` per item; `category2…5` are for custom actions and other tools. |
| Path Separator | `/` | Separator for the Path format. |

### Product Properties

Extra properties added to every product in every event. Zaraz GA4 forwards custom keys only at event level, so they
reach GA4 for single-product events (Product Viewed, Added, Removed, Clicked, Wishlist); inside `products` arrays they
are available to custom actions and other tools. Each property has an on/off switch and a property name.

| Field | Default name | Description |
|---|---|---|
| Stock Status | `stock_status` | `"in_stock"` / `"out_of_stock"`. Lists use the stock data already loaded for the listing, no extra queries. |
| Review Count | `review_count` | Number of approved reviews in the store (one query per list). |
| Rating | `rating` | Average rating 0–5 with one decimal, e.g. `4.3`. |
| On Sale | `on_sale` | `true` when the final price (special price, catalog rule) is lower than the regular price. |
| Custom Attributes | — | Any number of rows "property name ↔ product attribute". Select attributes send the store label of the option; for configurable lines the child value is used when it exists. |

All are off by default: every property makes payloads larger and is only useful if a tool or report uses it.

### User and Page Properties (zaraz.set)

Keys set with `zaraz.set()` are attached by Zaraz to later events and are available in the Zaraz dashboard as
*Track Property* (e.g. for a GA4 user property or a trigger condition). Each property has an on/off switch and a key.

| Field | Default | Default key | Description |
|---|---|---|---|
| Customer ID | No | `customer_id` | `"123"` for logged-in customers, scope session, unset on logout. Delivered through customer-data, never cached. |
| Customer Group | Yes | `customer_group` | Group code (`NOT LOGGED IN`, `General`, `Wholesale`…), scope session. |
| Page Type | Yes | `page_type` | `home`, `category`, `product`, `search`, `cart`, `checkout`, `purchase`, `cms`, `wishlist`, `compare`, `other`; scope page. |
| Page Name | No | `page_name` | Page title; scope page. |
| Email Hash (SHA-256) | No | `email_sha256` | SHA-256 of the trimmed lowercase email of a logged-in customer, scope session. The raw email is never sent. |

### List Attribution

| Field | Default | Description |
|---|---|---|
| Remember Lists | Yes | The browser remembers (in `localStorage`) the list a product was clicked in — `list_id`, `list_name`, `position` — and adds it to Product Viewed, Product Added / Removed, Cart Viewed, checkout events and Order Completed of that product. This is what fills the GA4 "Item list name" dimension for purchases. |
| Remember For (minutes) | 1440 | How long a click is attributed; 1440 = one day. |

### Google Ads Data

Extra properties in Order Completed for the Zaraz Google Ads tool. They are only data: the conversion itself
(conversion ID and label) is an action configured in the Zaraz dashboard. Discount, coupon and order totals are
standard Order Completed properties and are always sent.

| Field | Default | Description |
|---|---|---|
| Add Google Ads Data to Order Completed | No | Master switch for the fields below. |
| New Customer | Yes | `new_customer: true` when the customer (or the guest email) has no other non-cancelled orders — for "new customer acquisition" goals. |
| Customer Lifetime Value | Yes | `customer_lifetime_value`: all non-cancelled orders minus refunds, in base currency. |
| Cart Items | Yes | `ads_items: [{id, quantity, price}]` with the same id as `product_id` — for conversions with cart data and dynamic remarketing. |
| Enhanced Conversions | No | Adds `ec_*` properties from the billing address. Support by the Zaraz Google Ads tool is not confirmed by Cloudflare documentation. |
| Enhanced Conversions Fields | email, phone | Email, phone, names and street are SHA-256 of normalized values; city, region, postal code and country are plain, as Google requires. |
| Skip Zero Total Orders | Yes | No Ads data for orders with grand total 0 (Order Completed itself follows the Orders settings). |

### Preview Payload

**Marketing → Cloudflare Zaraz → Preview Payload** (ACL `Perspective_Zaraz::preview`): pick a product, an order or a
cart and see the exact events the module would generate with the current store view settings, and the names GA4,
Meta and TikTok will give them. Nothing is sent and nothing is saved, so it is safe to use on production while tuning
the settings above.

## How events are delivered

Full page cache is the main constraint: a cached page cannot contain per-visitor data, and the cart / login actions
happen on AJAX requests that render no page at all. The module therefore uses different channels:

| Event type | Mechanism |
|---|---|
| Page events (product, list, search, cart, checkout, success) | JSON inside `<script type="application/json" data-perspective-zaraz="events">`. It contains only public catalog data, so it is safe for FPC / Varnish. Cart, checkout and success pages are not cached anyway. |
| User actions (cart, wishlist, compare, coupon, login, sign-up) | queued in the session, then delivered through the `perspective-zaraz` customer-data section (read once). `sections.xml` invalidates the section on the relevant actions; the `perspective_zaraz_pending` cookie covers actions that do not invalidate sections. |
| Checkout steps, promotions, list clicks | server-rendered templates that the frontend adapter completes and sends on the browser action |
| Customer properties (`zaraz.set`) | the `perspective-zaraz` section after login or a customer group change |

Order Completed is not duplicated on page reload: a flag in the checkout session, plus the event id
`order-<increment_id>` that the JS core remembers in `localStorage`. Inline scripts are rendered through
`SecureHtmlRenderer` (CSP nonce), and in Manual script mode the Zaraz host is added to the CSP whitelist.

## JavaScript API

The core `view/frontend/web/js/perspective-zaraz.js` is plain ES5 without RequireJS or jQuery, so it works with any
frontend. The Luma adapter (`adapter/luma.js`, `luma-checkout.js`) reads customer-data sections and checkout steps.
Breeze, Hyvä or a PWA storefront needs its own small adapter that calls the same API:

```js
PerspectiveZaraz.push({
    method: 'ecommerce',              // ecommerce | track | set
    name: 'Product Added',
    payload: { product_id: 'MJ12', price: 45, quantity: 1, currency: 'USD' },
    id: 'pa-123',                     // optional: never send this id twice (localStorage)
    code: 'product_added'             // optional: module event code, used for list attribution
});

// before the core is loaded
(window.PerspectiveZarazQueue = window.PerspectiveZarazQueue || []).push(event);

PerspectiveZaraz.getTemplate('shipping_info_entered'); // server template, or null if the event is disabled
PerspectiveZaraz.onDelivered(function (event) {});     // Zaraz accepted the event (also replays earlier ones)
PerspectiveZaraz.getHistory(); PerspectiveZaraz.getPending(); PerspectiveZaraz.setDebug(true);
```

Events pushed before Zaraz is loaded stay in the queue and are sent as soon as `window.zaraz` appears.

## Extending

Everything is extended through DI pools; no preferences on module classes are needed.

| What | Interface | Pool in `di.xml` |
|---|---|---|
| New event | `Api\EventBuilderInterface` | `Model\Event\BuilderPool`, argument `builders` (key = event code) |
| Product properties | `Api\ProductDataModifierInterface`; for lists `Api\BatchProductDataModifierInterface` (preloads data for the whole list in one query) | `Model\Mapper\Modifier\ProductDataModifierPool` |
| Order properties | `Api\OrderDataModifierInterface` | `Model\Mapper\Modifier\OrderDataModifierPool` |
| New product list block | `Api\ProductListResolverInterface` + the `Plugin\Catalog\AppendListEvents` plugin on the block | `Model\ProductList\ResolverPool` |
| Channels of events without a row in "Events" | — | `Model\Event\Generator`, argument `defaultChannels` |
| Generate events from code | `Api\EventGeneratorInterface::generate($code, $context, $channel)` | — |

## Tests

```bash
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist vendor/perspectiveteam/module-zaraz/Test/Unit
cd dev/tests/integration && ../../../vendor/bin/phpunit -c phpunit.xml.dist ../../../vendor/perspectiveteam/module-zaraz/Test/Integration
vendor/bin/phpcs --standard=Magento2 --extensions=php,phtml,xml vendor/perspectiveteam/module-zaraz
```

Integration tests install a separate database. If `generated/metadata` exists (compiled DI), move it away while the
tests run, otherwise the test environment installation fails.

## Known limitations

- Product Clicked is sent at the moment of the click; if Zaraz has not loaded yet, the event can be lost (list
  attribution is still saved).
- Lists rendered in the browser (recently viewed / compared UI components) are not covered.
- Checkout templates contain the cart as it was when the checkout page loaded.
- Every `zaraz.track` / `zaraz.ecommerce` call is a billable Zaraz event above 1M per month; small list chunks and
  Viewed Promotion increase the count.
- Zaraz `dataLayer` compatibility mode does not support e-commerce, so the module does not use it.
