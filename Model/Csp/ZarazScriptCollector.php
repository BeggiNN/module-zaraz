<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Csp;

use Perspective\Zaraz\Model\Config;
use Magento\Csp\Api\PolicyCollectorInterface;
use Laminas\Uri\Http as HttpUri;
use Magento\Csp\Model\Policy\FetchPolicy;

class ZarazScriptCollector implements PolicyCollectorInterface
{
    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function collect(array $defaultPolicies = []): array
    {
        if (!$this->config->isEnabled() || !$this->config->isManualScript()) {
            return $defaultPolicies;
        }
        $uri = new HttpUri($this->config->getScriptUrl());
        if (!$uri->getHost()) {
            return $defaultPolicies;
        }
        $host = ($uri->getScheme() ?: 'https') . '://' . $uri->getHost()
            . ($uri->getPort() && !in_array($uri->getPort(), [80, 443], true) ? ':' . $uri->getPort() : '');
        $defaultPolicies[] = new FetchPolicy('script-src', false, [$host]);
        $defaultPolicies[] = new FetchPolicy('connect-src', false, [$host]);
        return $defaultPolicies;
    }
}
