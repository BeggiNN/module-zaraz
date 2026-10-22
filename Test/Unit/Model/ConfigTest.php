<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Unit\Model;

use Perspective\Zaraz\Model\Config;
use Perspective\Zaraz\Test\Unit\ConfigStubTrait;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    use ConfigStubTrait;

    /**
     * @return void
     */
    public function testScriptAttributesDropUnsafeAndControlledAttributes(): void
    {
        $config = $this->createConfig([
            Config::XML_SCRIPT_ATTRIBUTES => 'data-cfasync="false" async onload="alert(1)" src=evil.js data-x=\'y\'',
        ]);

        $this->assertSame(
            ['data-cfasync' => 'false', 'async' => '', 'data-x' => 'y'],
            $config->getScriptAttributes(1)
        );
    }

    /**
     * @return void
     */
    public function testEventSettingsAreDecodedByEventCode(): void
    {
        $config = $this->createConfig([
            Config::XML_EVENTS => json_encode([
                '_1' => [
                    'event' => 'product_added',
                    'enabled' => '1',
                    'name' => ' add ',
                    'browser' => '1',
                    'server' => '0',
                ],
                '_2' => ['event' => '', 'enabled' => '1'],
            ]),
        ]);

        $this->assertSame(
            ['enabled' => true, 'name' => 'add', 'browser' => true, 'server' => false],
            $config->getEventSettings('product_added', 1)
        );
        $this->assertNull($config->getEventSettings('order_completed', 1));
    }

    /**
     * @return void
     */
    public function testBrokenEventsJsonMeansNoRows(): void
    {
        $this->assertNull($this->createConfig([Config::XML_EVENTS => '{broken'])->getEventSettings('product_added'));
    }

    /**
     * @return void
     */
    public function testListsAndFallbacks(): void
    {
        $config = $this->createConfig([
            Config::XML_DEBUG_IPS => " 10.0.0.1, 10.0.0.2\n127.0.0.1 ",
            Config::XML_ORDER_SKIP_STATUSES => 'fraud,holded',
            Config::XML_BRAND_ATTRIBUTE => 'manufacturer',
        ]);

        $this->assertSame(['10.0.0.1', '10.0.0.2', '127.0.0.1'], $config->getDebugIps());
        $this->assertSame(['fraud', 'holded'], $config->getSkipOrderStatuses());
        $this->assertSame('/cdn-cgi/zaraz/i.js', $config->getScriptUrl());
        $this->assertNull($config->getBrandAttribute(), 'brand is disabled by default');
        $this->assertSame(
            'manufacturer',
            $this->createConfig([Config::XML_BRAND_ENABLED => '1', Config::XML_BRAND_ATTRIBUTE => 'manufacturer'])
                ->getBrandAttribute()
        );
    }

    /**
     * @return void
     */
    public function testPhaseTwoSettings(): void
    {
        $config = $this->createConfig([
            Config::XML_LIST_CHUNK_SIZE => '0',
            'perspective_zaraz/properties/stock_status' => '1',
            'perspective_zaraz/properties/stock_status_key' => 'availability',
            'perspective_zaraz/properties/rating' => '1',
            Config::XML_CUSTOM_ATTRIBUTES => json_encode([
                '_1' => ['property' => 'material', 'attribute' => 'material'],
                '_2' => ['property' => '', 'attribute' => 'color'],
            ]),
            'perspective_zaraz/ads/new_customer' => '1',
            Config::XML_ATTRIBUTION_ENABLED => '1',
            Config::XML_ATTRIBUTION_TTL => '30',
        ]);

        $this->assertSame(20, $config->getListChunkSize(), 'invalid size falls back to 20');
        $this->assertSame('availability', $config->getProductPropertyKey('stock_status'));
        $this->assertSame('rating', $config->getProductPropertyKey('rating'), 'empty key = property name');
        $this->assertNull($config->getProductPropertyKey('on_sale'));
        $this->assertSame(['material' => 'material'], $config->getCustomAttributes());
        $this->assertFalse($config->isAdsOption('new_customer'), 'requires ads/enabled');
        $this->assertSame(['enabled' => true, 'ttl' => 30], $config->getAttribution());
        $this->assertSame('/', $config->getCategorySeparator());
    }
}
