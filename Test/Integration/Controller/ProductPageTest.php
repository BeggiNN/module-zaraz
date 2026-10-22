<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Integration\Controller;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Product page output of the module
 */
#[DbIsolation(true)]
class ProductPageTest extends AbstractController
{
    /**
     * @return string
     */
    private function dispatchProductPage(): string
    {
        $product = DataFixtureStorageManager::getStorage()->get('product');
        $this->dispatch('catalog/product/view/id/' . $product->getId());
        return html_entity_decode((string)$this->getResponse()->getBody());
    }

    /**
     * @return void
     */
    #[DataFixture(ProductFixture::class, ['sku' => 'zaraz-pdp', 'price' => 15], 'product')]
    public function testProductViewedAndCoreAreRendered(): void
    {
        $body = $this->dispatchProductPage();

        $this->assertStringContainsString('id="perspective-zaraz-config"', $body);
        $this->assertStringContainsString('Perspective_Zaraz/js/perspective-zaraz.js', $body);
        $this->assertStringContainsString('data-perspective-zaraz="events"', $body);
        $this->assertMatchesRegularExpression(
            '/"name":"Product Viewed","payload":\{"product_id":"zaraz-pdp".*"price":15/',
            $body
        );
        $this->assertStringNotContainsString('/cdn-cgi/zaraz/i.js', $body, 'auto-inject mode prints no Zaraz script');
    }

    /**
     * @return void
     */
    #[Config('perspective_zaraz/general/script_mode', 'manual', 'store', 'default')]
    #[DataFixture(ProductFixture::class, ['price' => 15], 'product')]
    public function testManualZarazScript(): void
    {
        $this->assertMatchesRegularExpression(
            '#<script src="http://localhost/cdn-cgi/zaraz/i\.js" referrerpolicy="origin"#',
            $this->dispatchProductPage()
        );
    }

    /**
     * @return void
     */
    #[Config('perspective_zaraz/general/enabled', '0', 'store', 'default')]
    #[DataFixture(ProductFixture::class, ['price' => 15], 'product')]
    public function testDisabledModulePrintsNothing(): void
    {
        $body = $this->dispatchProductPage();

        // Only the static customer-data section name stays registered; it returns empty data when disabled
        $this->assertStringNotContainsString('perspective-zaraz-config', $body);
        $this->assertStringNotContainsString('perspective-zaraz.js', $body);
        $this->assertStringNotContainsString('data-perspective-zaraz', $body);
        $this->assertStringNotContainsString('Perspective_Zaraz/js/adapter/luma', $body);
    }
}
