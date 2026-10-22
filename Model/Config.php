<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed access to perspective_zaraz/* store configuration
 */
class Config
{
    public const XML_ENABLED = 'perspective_zaraz/general/enabled';
    public const XML_DEBUG = 'perspective_zaraz/general/debug';
    public const XML_DEBUG_IPS = 'perspective_zaraz/general/debug_ips';
    public const XML_SCRIPT_MODE = 'perspective_zaraz/general/script_mode';
    public const XML_SCRIPT_URL = 'perspective_zaraz/general/script_url';
    public const XML_SCRIPT_ATTRIBUTES = 'perspective_zaraz/general/script_attributes';

    public const XML_EVENTS = 'perspective_zaraz/events/events';

    public const XML_PRODUCT_ID_SOURCE = 'perspective_zaraz/product/id_source';
    public const XML_PRODUCT_SEND_SKU = 'perspective_zaraz/product/send_sku';
    public const XML_CONFIGURABLE_MODE = 'perspective_zaraz/product/configurable_mode';
    public const XML_VARIANT_ENABLED = 'perspective_zaraz/product/variant_enabled';
    public const XML_VARIANT_SOURCE = 'perspective_zaraz/product/variant_source';
    public const XML_BRAND_ENABLED = 'perspective_zaraz/product/brand_enabled';
    public const XML_BRAND_ATTRIBUTE = 'perspective_zaraz/product/brand_attribute';

    public const XML_CURRENCY_SCOPE = 'perspective_zaraz/price/currency_scope';
    public const XML_PRODUCT_PRICE_TAX = 'perspective_zaraz/price/product_price_tax';
    public const XML_ORDER_TOTAL_SOURCE = 'perspective_zaraz/price/order_total_source';
    public const XML_EXCLUDE_TAX = 'perspective_zaraz/price/exclude_tax';
    public const XML_EXCLUDE_SHIPPING = 'perspective_zaraz/price/exclude_shipping';
    public const XML_EXCLUDE_SHIPPING_TAX = 'perspective_zaraz/price/exclude_shipping_tax';
    public const XML_SHIPPING_INCL_TAX = 'perspective_zaraz/price/shipping_incl_tax';

    public const XML_ORDER_SKIP_ZERO = 'perspective_zaraz/order/skip_zero_total';
    public const XML_ORDER_SKIP_STATUSES = 'perspective_zaraz/order/skip_statuses';
    public const XML_ORDER_AFFILIATION = 'perspective_zaraz/order/affiliation';

    public const XML_LIST_CHUNK_SIZE = 'perspective_zaraz/general/list_chunk_size';
    public const XML_CHECKOUT_PATHS = 'perspective_zaraz/general/checkout_paths';
    public const XML_SUCCESS_PATHS = 'perspective_zaraz/general/success_paths';
    public const XML_VIEW_SEND_CHILDREN = 'perspective_zaraz/product/view_send_children';

    public const XML_CATEGORY_EXCLUDED = 'perspective_zaraz/category/excluded';
    public const XML_CATEGORY_NEXT_AVAILABLE = 'perspective_zaraz/category/use_next_available';
    public const XML_CATEGORY_DEPTH = 'perspective_zaraz/category/depth_limit';
    public const XML_CATEGORY_DEPTH_DIRECTION = 'perspective_zaraz/category/depth_direction';
    public const XML_CATEGORY_FORMAT = 'perspective_zaraz/category/format';
    public const XML_CATEGORY_SEPARATOR = 'perspective_zaraz/category/separator';

    public const XML_PROPERTIES = 'perspective_zaraz/properties/';
    public const XML_CUSTOM_ATTRIBUTES = 'perspective_zaraz/properties/custom_attributes';

    public const XML_USER = 'perspective_zaraz/user/';

    public const XML_ATTRIBUTION_ENABLED = 'perspective_zaraz/attribution/enabled';
    public const XML_ATTRIBUTION_TTL = 'perspective_zaraz/attribution/ttl_minutes';

    public const XML_ADS = 'perspective_zaraz/ads/';
    public const XML_ADS_EC_FIELDS = 'perspective_zaraz/ads/ec_fields';

    public const CATEGORY_FORMAT_PATH = 'path';
    public const CATEGORY_FORMAT_LEVELS = 'levels';
    public const DEPTH_FROM_ROOT = 'root';
    public const DEPTH_FROM_LEAF = 'leaf';

    public const SCRIPT_MODE_AUTO = 'auto';
    public const SCRIPT_MODE_MANUAL = 'manual';
    public const ID_SOURCE_SKU = 'sku';
    public const ID_SOURCE_ENTITY = 'entity_id';
    public const CONFIGURABLE_PARENT = 'parent';
    public const CONFIGURABLE_CHILD = 'child';
    public const VARIANT_OPTIONS = 'options';
    public const VARIANT_CHILD_SKU = 'child_sku';
    public const VARIANT_CHILD_NAME = 'child_name';
    public const CURRENCY_BASE = 'base';
    public const CURRENCY_DISPLAY = 'display';
    public const TOTAL_GRAND_TOTAL = 'grand_total';
    public const TOTAL_SUBTOTAL = 'subtotal';

    /**
     * Decoded "Events" rows by raw config value (a changed value is decoded again, e.g. in long-running consumers)
     *
     * @var array<string, array<string, array>>
     */
    private array $eventSettings = [];

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Json $json
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Json $json,
    ) {
    }

    /**
     * Module master switch
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_ENABLED, $storeId);
    }

    /**
     * Debug console logging switch (IP restriction is checked separately)
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isDebug(?int $storeId = null): bool
    {
        return $this->flag(self::XML_DEBUG, $storeId);
    }

    /**
     * IPs allowed to see debug output; empty = everyone
     *
     * @param int|null $storeId
     * @return string[]
     */
    public function getDebugIps(?int $storeId = null): array
    {
        return $this->splitList($this->value(self::XML_DEBUG_IPS, $storeId), '/[\s,]+/');
    }

    /**
     * Whether the module must print the Zaraz script tag (auto-inject disabled in Cloudflare)
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isManualScript(?int $storeId = null): bool
    {
        return $this->value(self::XML_SCRIPT_MODE, $storeId) === self::SCRIPT_MODE_MANUAL;
    }

    /**
     * URL of the Zaraz script for manual mode
     *
     * @param int|null $storeId
     * @return string
     */
    public function getScriptUrl(?int $storeId = null): string
    {
        return trim($this->value(self::XML_SCRIPT_URL, $storeId)) ?: '/cdn-cgi/zaraz/i.js';
    }

    /**
     * Extra attributes for module script tags, e.g. ['data-cfasync' => 'false']
     *
     * Event handler attributes (on*) and attributes the module controls are dropped.
     *
     * @param int|null $storeId
     * @return array<string, string>
     */
    public function getScriptAttributes(?int $storeId = null): array
    {
        $raw = $this->value(self::XML_SCRIPT_ATTRIBUTES, $storeId);
        $pattern = '/([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+)))?/';
        preg_match_all($pattern, $raw, $matches, PREG_SET_ORDER);
        $attributes = [];
        foreach ($matches as $match) {
            $name = strtolower($match[1]);
            if (str_starts_with($name, 'on') || in_array($name, ['src', 'type', 'id', 'nonce'], true)) {
                continue;
            }
            $attributes[$name] = (string)($match[2] ?? '') . ($match[3] ?? '') . ($match[4] ?? '');
        }
        return $attributes;
    }

    /**
     * Admin row of the "Events" table for an event code, null when there is no row
     *
     * @param string $code
     * @param int|null $storeId
     * @return array{enabled: bool, name: string, browser: bool, server: bool}|null
     */
    public function getEventSettings(string $code, ?int $storeId = null): ?array
    {
        $raw = $this->value(self::XML_EVENTS, $storeId);
        if (!isset($this->eventSettings[$raw])) {
            $this->eventSettings[$raw] = $this->decodeEventSettings($raw);
        }
        return $this->eventSettings[$raw][$code] ?? null;
    }

    /**
     * Product identifier sent as product_id: sku | entity_id
     *
     * @param int|null $storeId
     * @return string
     */
    public function getProductIdSource(?int $storeId = null): string
    {
        return $this->value(self::XML_PRODUCT_ID_SOURCE, $storeId) ?: self::ID_SOURCE_SKU;
    }

    /**
     * Whether the separate "sku" property is sent
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSendSku(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PRODUCT_SEND_SKU, $storeId);
    }

    /**
     * Configurable product identity: parent | child
     *
     * @param int|null $storeId
     * @return string
     */
    public function getConfigurableMode(?int $storeId = null): string
    {
        return $this->value(self::XML_CONFIGURABLE_MODE, $storeId) ?: self::CONFIGURABLE_PARENT;
    }

    /**
     * Whether "variant" is sent
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isVariantEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_VARIANT_ENABLED, $storeId);
    }

    /**
     * Variant source: options | child_sku | child_name
     *
     * @param int|null $storeId
     * @return string
     */
    public function getVariantSource(?int $storeId = null): string
    {
        return $this->value(self::XML_VARIANT_SOURCE, $storeId) ?: self::VARIANT_OPTIONS;
    }

    /**
     * Brand attribute code, or null when brand is disabled
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getBrandAttribute(?int $storeId = null): ?string
    {
        if (!$this->flag(self::XML_BRAND_ENABLED, $storeId)) {
            return null;
        }
        return trim($this->value(self::XML_BRAND_ATTRIBUTE, $storeId)) ?: null;
    }

    /**
     * Whether amounts are sent in base currency (otherwise display/order currency)
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isBaseCurrency(?int $storeId = null): bool
    {
        return $this->value(self::XML_CURRENCY_SCOPE, $storeId) === self::CURRENCY_BASE;
    }

    /**
     * Whether product prices include tax
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isProductPriceInclTax(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PRODUCT_PRICE_TAX, $storeId);
    }

    /**
     * Order "total" source: grand_total | subtotal
     *
     * @param int|null $storeId
     * @return string
     */
    public function getOrderTotalSource(?int $storeId = null): string
    {
        return $this->value(self::XML_ORDER_TOTAL_SOURCE, $storeId) ?: self::TOTAL_GRAND_TOTAL;
    }

    /**
     * Exclude all tax from "total"
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isExcludeTax(?int $storeId = null): bool
    {
        return $this->flag(self::XML_EXCLUDE_TAX, $storeId);
    }

    /**
     * Exclude shipping (without its tax) from "total"
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isExcludeShipping(?int $storeId = null): bool
    {
        return $this->flag(self::XML_EXCLUDE_SHIPPING, $storeId);
    }

    /**
     * Exclude shipping tax from "total" (only when all tax is not excluded)
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isExcludeShippingTax(?int $storeId = null): bool
    {
        return $this->flag(self::XML_EXCLUDE_SHIPPING_TAX, $storeId);
    }

    /**
     * Whether the "shipping" property includes shipping tax
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isShippingInclTax(?int $storeId = null): bool
    {
        return $this->flag(self::XML_SHIPPING_INCL_TAX, $storeId);
    }

    /**
     * Do not send orders with zero grand total
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isSkipZeroTotalOrders(?int $storeId = null): bool
    {
        return $this->flag(self::XML_ORDER_SKIP_ZERO, $storeId);
    }

    /**
     * Order statuses that are never sent
     *
     * @param int|null $storeId
     * @return string[]
     */
    public function getSkipOrderStatuses(?int $storeId = null): array
    {
        return $this->splitList($this->value(self::XML_ORDER_SKIP_STATUSES, $storeId), '/\s*,\s*/');
    }

    /**
     * Fixed "affiliation" value; empty = store name
     *
     * @param int|null $storeId
     * @return string
     */
    public function getAffiliation(?int $storeId = null): string
    {
        return trim($this->value(self::XML_ORDER_AFFILIATION, $storeId));
    }

    /**
     * Products per "Product List Viewed" event
     *
     * @param int|null $storeId
     * @return int
     */
    public function getListChunkSize(?int $storeId = null): int
    {
        return max(1, (int)$this->value(self::XML_LIST_CHUNK_SIZE, $storeId) ?: 20);
    }

    /**
     * Request paths or full action names treated as checkout (custom checkouts)
     *
     * @param int|null $storeId
     * @return string[]
     */
    public function getCheckoutPaths(?int $storeId = null): array
    {
        return $this->splitList($this->value(self::XML_CHECKOUT_PATHS, $storeId), '/\s*[\r\n,]+\s*/');
    }

    /**
     * Request paths or full action names treated as checkout success (custom checkouts)
     *
     * @param int|null $storeId
     * @return string[]
     */
    public function getSuccessPaths(?int $storeId = null): array
    {
        return $this->splitList($this->value(self::XML_SUCCESS_PATHS, $storeId), '/\s*[\r\n,]+\s*/');
    }

    /**
     * Product Viewed of a configurable lists all its children in "products"
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isViewSendChildren(?int $storeId = null): bool
    {
        return $this->flag(self::XML_VIEW_SEND_CHILDREN, $storeId);
    }

    /**
     * Category ids excluded from category properties
     *
     * @param int|null $storeId
     * @return int[]
     */
    public function getExcludedCategories(?int $storeId = null): array
    {
        return array_map('intval', $this->splitList($this->value(self::XML_CATEGORY_EXCLUDED, $storeId), '/\s*,\s*/'));
    }

    /**
     * When the deepest category of a product is excluded, use its next available category
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isCategoryUseNextAvailable(?int $storeId = null): bool
    {
        return $this->flag(self::XML_CATEGORY_NEXT_AVAILABLE, $storeId);
    }

    /**
     * Maximum number of category levels (0 = all)
     *
     * @param int|null $storeId
     * @return int
     */
    public function getCategoryDepth(?int $storeId = null): int
    {
        return max(0, (int)$this->value(self::XML_CATEGORY_DEPTH, $storeId));
    }

    /**
     * Which levels are kept when the depth is limited: root | leaf
     *
     * @param int|null $storeId
     * @return string
     */
    public function getCategoryDepthDirection(?int $storeId = null): string
    {
        return $this->value(self::XML_CATEGORY_DEPTH_DIRECTION, $storeId) ?: self::DEPTH_FROM_ROOT;
    }

    /**
     * Category format: path (one string) | levels (category, category2 … category5)
     *
     * @param int|null $storeId
     * @return string
     */
    public function getCategoryFormat(?int $storeId = null): string
    {
        return $this->value(self::XML_CATEGORY_FORMAT, $storeId) ?: self::CATEGORY_FORMAT_PATH;
    }

    /**
     * Separator of the category path
     *
     * @param int|null $storeId
     * @return string
     */
    public function getCategorySeparator(?int $storeId = null): string
    {
        $separator = (string)$this->scopeConfig->getValue(
            self::XML_CATEGORY_SEPARATOR,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $separator !== '' ? $separator : '/';
    }

    /**
     * Property key of an optional product property (stock_status, review_count, rating, on_sale) or null when off
     *
     * @param string $property
     * @param int|null $storeId
     * @return string|null
     */
    public function getProductPropertyKey(string $property, ?int $storeId = null): ?string
    {
        if (!$this->flag(self::XML_PROPERTIES . $property, $storeId)) {
            return null;
        }
        return trim($this->value(self::XML_PROPERTIES . $property . '_key', $storeId)) ?: $property;
    }

    /**
     * Custom attribute properties: [property name => attribute code]
     *
     * @param int|null $storeId
     * @return array<string, string>
     */
    public function getCustomAttributes(?int $storeId = null): array
    {
        $result = [];
        foreach ($this->decodeRows($this->value(self::XML_CUSTOM_ATTRIBUTES, $storeId)) as $row) {
            $property = trim((string)($row['property'] ?? ''));
            $attribute = trim((string)($row['attribute'] ?? ''));
            if ($property !== '' && $attribute !== '') {
                $result[$property] = $attribute;
            }
        }
        return $result;
    }

    /**
     * Key of a zaraz.set() user/page property or null when off
     *
     * Properties: customer_id, customer_group, page_type, page_name, email_hash.
     *
     * @param string $property
     * @param int|null $storeId
     * @return string|null
     */
    public function getUserPropertyKey(string $property, ?int $storeId = null): ?string
    {
        if (!$this->flag(self::XML_USER . $property, $storeId)) {
            return null;
        }
        return trim($this->value(self::XML_USER . $property . '_key', $storeId)) ?: $property;
    }

    /**
     * List attribution: [enabled, ttl in minutes]
     *
     * @param int|null $storeId
     * @return array{enabled: bool, ttl: int}
     */
    public function getAttribution(?int $storeId = null): array
    {
        return [
            'enabled' => $this->flag(self::XML_ATTRIBUTION_ENABLED, $storeId),
            'ttl' => max(1, (int)$this->value(self::XML_ATTRIBUTION_TTL, $storeId) ?: 1440),
        ];
    }

    /**
     * Google Ads data option
     *
     * Options: enabled, new_customer, lifetime_value, cart_items, enhanced_conversions, skip_zero_total;
     * every option except "enabled" requires "enabled".
     *
     * @param string $option
     * @param int|null $storeId
     * @return bool
     */
    public function isAdsOption(string $option, ?int $storeId = null): bool
    {
        if (!$this->flag(self::XML_ADS . 'enabled', $storeId)) {
            return false;
        }
        return $option === 'enabled' || $this->flag(self::XML_ADS . $option, $storeId);
    }

    /**
     * Enhanced Conversions fields
     *
     * @param int|null $storeId
     * @return string[]
     */
    public function getEnhancedConversionFields(?int $storeId = null): array
    {
        return $this->splitList($this->value(self::XML_ADS_EC_FIELDS, $storeId), '/\s*,\s*/');
    }

    /**
     * Read store-scope config flag
     *
     * @param string $path
     * @param int|null $storeId
     * @return bool
     */
    private function flag(string $path, ?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Read store-scope config value as string
     *
     * @param string $path
     * @param int|null $storeId
     * @return string
     */
    private function value(string $path, ?int $storeId): string
    {
        return (string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Split a list value into non-empty items
     *
     * @param string $value
     * @param string $pattern
     * @return string[]
     */
    private function splitList(string $value, string $pattern): array
    {
        return array_values(array_filter(preg_split($pattern, trim($value)) ?: [], 'strlen'));
    }

    /**
     * Decode the serialized "Events" dynamic rows into [code => settings]
     *
     * @param string $raw
     * @return array
     */
    private function decodeEventSettings(string $raw): array
    {
        $settings = [];
        foreach ($this->decodeRows($raw) as $row) {
            if (empty($row['event'])) {
                continue;
            }
            $settings[(string)$row['event']] = [
                'enabled' => !empty($row['enabled']),
                'name' => trim((string)($row['name'] ?? '')),
                'browser' => !empty($row['browser']),
                'server' => !empty($row['server']),
            ];
        }
        return $settings;
    }

    /**
     * Decode serialized dynamic rows
     *
     * @param string $raw
     * @return array[]
     */
    private function decodeRows(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        try {
            $rows = $this->json->unserialize($raw);
        } catch (\InvalidArgumentException) {
            return [];
        }
        return array_values(array_filter(is_array($rows) ? $rows : [], 'is_array'));
    }
}
