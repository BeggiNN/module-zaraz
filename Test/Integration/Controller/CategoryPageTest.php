<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Integration\Controller;

use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Indexer\Test\Fixture\Indexer as IndexerFixture;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Product List Viewed (chunks) and Product Clicked templates on a category page.
 *
 * Category listings come from the search engine, so the data is reindexed; the search index lives outside the DB
 * transaction, therefore fixtures are reverted instead of rolled back.
 */
#[DbIsolation(false)]
class CategoryPageTest extends AbstractController
{
    /**
     * JSON of the module scripts of a type
     *
     * @param string $body
     * @param string $type
     * @return array
     */
    private function scripts(string $body, string $type): array
    {
        preg_match_all(
            '#<script type="application/json" data-perspective-zaraz="' . $type . '">(.*?)</script>#s',
            $body,
            $matches
        );
        return array_map(static fn (string $json): array => json_decode($json, true), $matches[1]);
    }

    /**
     * @return void
     */
    #[Config('perspective_zaraz/general/list_chunk_size', '2', 'store', 'default')]
    #[DataFixture(CategoryFixture::class, ['name' => 'Zaraz Category', 'is_anchor' => 1], 'category')]
    #[DataFixture(ProductFixture::class, ['sku' => 'zaraz-list-1', 'category_ids' => ['$category.id$']], 'p1')]
    #[DataFixture(ProductFixture::class, ['sku' => 'zaraz-list-2', 'category_ids' => ['$category.id$']], 'p2')]
    #[DataFixture(ProductFixture::class, ['sku' => 'zaraz-list-3', 'category_ids' => ['$category.id$']], 'p3')]
    #[DataFixture(IndexerFixture::class)]
    public function testListEventsInChunksAndClickTemplates(): void
    {
        $storage = DataFixtureStorageManager::getStorage();
        $categoryId = (int)$storage->get('category')->getId();
        $this->dispatch('catalog/category/view/id/' . $categoryId);
        $body = html_entity_decode((string)$this->getResponse()->getBody());

        $lists = array_merge(...$this->scripts($body, 'events'));
        $chunks = array_values(
            array_filter($lists, static fn (array $e): bool => $e['name'] === 'Product List Viewed')
        );
        $this->assertCount(2, $chunks, '3 products, chunk size 2');
        $this->assertSame('category_' . $categoryId, $chunks[0]['payload']['list_id']);
        $this->assertSame('Zaraz Category', $chunks[0]['payload']['category']);
        $this->assertCount(2, $chunks[0]['payload']['products']);
        $this->assertSame(3, $chunks[1]['payload']['products'][0]['position']);
        $this->assertSame('Zaraz Category', $chunks[1]['payload']['products'][0]['list_name']);

        $click = $this->scripts($body, 'list')[0];
        $this->assertSame('category_' . $categoryId, $click['list_id']);
        $template = $click['clicks'][(string)$storage->get('p2')->getId()];
        $this->assertSame('Product Clicked', $template['name']);
        $this->assertSame('product_clicked', $template['code']);
        $this->assertSame('zaraz-list-2', $template['payload']['product_id']);
        $this->assertSame('category_' . $categoryId, $template['payload']['list_id']);
        $this->assertIsInt($template['payload']['position']);
    }

    /**
     * @return void
     */
    #[Config('perspective_zaraz/general/enabled', '0', 'store', 'default')]
    #[DataFixture(CategoryFixture::class, as: 'category')]
    #[DataFixture(ProductFixture::class, ['category_ids' => ['$category.id$']])]
    #[DataFixture(IndexerFixture::class)]
    public function testDisabledModuleAddsNothingToLists(): void
    {
        $categoryId = DataFixtureStorageManager::getStorage()->get('category')->getId();
        $this->dispatch('catalog/category/view/id/' . $categoryId);
        $this->assertStringNotContainsString('data-perspective-zaraz', (string)$this->getResponse()->getBody());
    }
}
