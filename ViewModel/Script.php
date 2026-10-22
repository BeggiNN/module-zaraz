<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\ViewModel;

use Perspective\Zaraz\Model\Config;
use Perspective\Zaraz\Model\Request\DebugAllowed;
use Laminas\Uri\Http as HttpUri;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Data for the head scripts: core config, Zaraz script (manual mode), script attributes
 */
class Script implements ArgumentInterface
{
    public const CORE_VERSION = '0.1.0';

    /**
     * @param Config $config
     * @param DebugAllowed $debugAllowed
     * @param StoreManagerInterface $storeManager
     * @param JsonHexTag $json
     */
    public function __construct(
        private readonly Config $config,
        private readonly DebugAllowed $debugAllowed,
        private readonly StoreManagerInterface $storeManager,
        private readonly JsonHexTag $json,
    ) {
    }

    /**
     * Core configuration JSON; contains only store-level (cacheable) values
     *
     * @return string
     */
    public function getConfigJson(): string
    {
        $storeId = $this->getStoreId();
        return $this->json->serialize([
            'version' => self::CORE_VERSION,
            'debug' => $this->debugAllowed->isPublic($storeId),
            'attribution' => $this->config->getAttribution($storeId),
        ]);
    }

    /**
     * Whether the module prints the Zaraz script itself
     *
     * @return bool
     */
    public function isManualScript(): bool
    {
        return $this->config->isManualScript($this->getStoreId());
    }

    /**
     * Absolute Zaraz script URL for manual mode
     *
     * A path ("/cdn-cgi/zaraz/i.js") is resolved against the store origin, not the base URL: Zaraz is served from
     * the domain root even when store codes are added to URLs. Absolute URL also lets CSP whitelist the host.
     *
     * @return string
     */
    public function getZarazScriptUrl(): string
    {
        $url = $this->config->getScriptUrl($this->getStoreId());
        if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return $url;
        }
        /** @var Store $store */
        $store = $this->storeManager->getStore();
        $base = new HttpUri($store->getBaseUrl(UrlInterface::URL_TYPE_WEB, $store->isCurrentlySecure()));
        $port = $base->getPort();
        $origin = $base->getScheme() . '://' . $base->getHost()
            . ($port && !in_array($port, [80, 443], true) ? ':' . $port : '');

        return $origin . $url;
    }

    /**
     * Admin-defined attributes for module script tags (e.g. data-cfasync="false" for Rocket Loader)
     *
     * @return array<string, string>
     */
    public function getScriptAttributes(): array
    {
        return $this->config->getScriptAttributes($this->getStoreId());
    }

    /**
     * Current store id
     *
     * @return int
     */
    private function getStoreId(): int
    {
        return (int)$this->storeManager->getStore()->getId();
    }
}
