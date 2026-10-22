<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Request;

use Perspective\Zaraz\Model\Config;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;

/**
 * Debug mode resolution.
 *
 * Without IP restriction the flag is public and goes into the (cacheable) page config.
 * With IP restriction it is resolved per visitor in the private customer-data section, so FPC never caches it.
 * Behind Varnish/Cloudflare configure the real client IP header for RemoteAddress
 * (argument "alternativeHeaders", e.g. HTTP_CF_CONNECTING_IP).
 */
class DebugAllowed
{
    /**
     * @param Config $config
     * @param RemoteAddress $remoteAddress
     */
    public function __construct(
        private readonly Config $config,
        private readonly RemoteAddress $remoteAddress,
    ) {
    }

    /**
     * Debug for every visitor (safe for full page cache)
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isPublic(?int $storeId = null): bool
    {
        return $this->config->isDebug($storeId) && !$this->config->getDebugIps($storeId);
    }

    /**
     * Debug restricted to IPs (resolved in the private section)
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isIpRestricted(?int $storeId = null): bool
    {
        return $this->config->isDebug($storeId) && (bool)$this->config->getDebugIps($storeId);
    }

    /**
     * Debug for the current visitor
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isAllowedForCurrentVisitor(?int $storeId = null): bool
    {
        if (!$this->config->isDebug($storeId)) {
            return false;
        }
        $ips = $this->config->getDebugIps($storeId);
        return !$ips || in_array((string)$this->remoteAddress->getRemoteAddress(), $ips, true);
    }
}
