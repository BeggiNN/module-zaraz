<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Unit;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Perspective\Zaraz\Model\Config;

/**
 * Real Config over an in-memory store configuration (defaults of etc/config.xml + overrides)
 */
trait ConfigStubTrait
{
    /**
     * Config with the module defaults and the given path => value overrides
     *
     * @param array $values
     * @return Config
     */
    private function createConfig(array $values = []): Config
    {
        $values += [
            Config::XML_ENABLED => '1',
            Config::XML_PRODUCT_ID_SOURCE => Config::ID_SOURCE_SKU,
            Config::XML_PRODUCT_SEND_SKU => '1',
            Config::XML_CONFIGURABLE_MODE => Config::CONFIGURABLE_PARENT,
            Config::XML_VARIANT_ENABLED => '1',
            Config::XML_VARIANT_SOURCE => Config::VARIANT_OPTIONS,
            Config::XML_CURRENCY_SCOPE => Config::CURRENCY_DISPLAY,
            Config::XML_PRODUCT_PRICE_TAX => '0',
            Config::XML_ORDER_TOTAL_SOURCE => Config::TOTAL_GRAND_TOTAL,
            Config::XML_ORDER_SKIP_ZERO => '1',
        ];
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): mixed => $values[$path] ?? null
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path): bool => !empty($values[$path])
        );

        return new Config($scopeConfig, new Json());
    }
}
