<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Test\Unit\Model\Category;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\Zaraz\Model\Category\CategoryResolver;
use Perspective\Zaraz\Model\Config;
use Perspective\Zaraz\Test\Unit\ConfigStubTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryResolverTest extends TestCase
{
    use ConfigStubTrait;

    /**
     * Tree under root 2: Men(10) / Tops(11) / Jackets(12); Sale(20, level 2); inactive Old(30) / Archive(31)
     */
    private const CATEGORIES = [
        2 => ['1/2', 1, 'Default Category'],
        10 => ['1/2/10', 2, 'Men'],
        11 => ['1/2/10/11', 3, 'Tops'],
        12 => ['1/2/10/11/12', 4, 'Jackets'],
        20 => ['1/2/20', 2, 'Sale'],
        30 => ['1/2/30', 2, 'Old'],
        31 => ['1/2/30/31', 3, 'Archive'],
        40 => ['1/99/40', 2, 'Other store root'],
    ];

    /**
     * @var int
     */
    private int $queries = 0;

    /**
     * @param array $settings
     * @return CategoryResolver
     */
    private function createResolver(array $settings): CategoryResolver
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturnCallback(function (): Collection {
            $ids = [];
            $collection = $this->getMockBuilder(Collection::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['setStoreId', 'addAttributeToSelect', 'addIdFilter', 'getIterator'])
                ->getMock();
            $collection->method('setStoreId')->willReturnSelf();
            $collection->method('addAttributeToSelect')->willReturnSelf();
            $collection->method('addIdFilter')->willReturnCallback(
                function (array $filter) use (&$ids, $collection) {
                    $ids = $filter;
                    return $collection;
                }
            );
            $collection->method('getIterator')->willReturnCallback(function () use (&$ids): \ArrayIterator {
                $this->queries++;
                $items = [];
                foreach ($ids as $id) {
                    if (isset(self::CATEGORIES[$id])) {
                        [$path, $level, $name] = self::CATEGORIES[$id];
                        $category = $this->getMockBuilder(Category::class)
                            ->disableOriginalConstructor()
                            ->onlyMethods(['getId', 'getPath', 'getLevel', 'getName', 'getIsActive'])
                            ->getMock();
                        $category->method('getId')->willReturn($id);
                        $category->method('getPath')->willReturn($path);
                        $category->method('getLevel')->willReturn($level);
                        $category->method('getName')->willReturn($name);
                        $category->method('getIsActive')->willReturn($id !== 30 && $id !== 31);
                        $items[] = $category;
                    }
                }
                return new \ArrayIterator($items);
            });
            return $collection;
        });
        $store = $this->createMock(Store::class);
        $store->method('getRootCategoryId')->willReturn(2);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new CategoryResolver($factory, $storeManager, $this->createConfig($settings));
    }

    /**
     * @param int[] $categoryIds
     * @return Product
     */
    private function product(array $categoryIds): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getCategoryIds')->willReturn($categoryIds);
        return $product;
    }

    /**
     * @return array
     */
    public static function settingsProvider(): array
    {
        $levels = [Config::XML_CATEGORY_FORMAT => Config::CATEGORY_FORMAT_LEVELS];
        return [
            'deepest active category path' => [[], [12, 20, 31, 40], ['category' => 'Men/Tops/Jackets']],
            'custom separator' => [
                [Config::XML_CATEGORY_SEPARATOR => ' > '],
                [12],
                ['category' => 'Men > Tops > Jackets'],
            ],
            'levels' => [$levels, [12], ['category' => 'Men', 'category2' => 'Tops', 'category3' => 'Jackets']],
            'depth 2 from root' => [[Config::XML_CATEGORY_DEPTH => '2'], [12], ['category' => 'Men/Tops']],
            'depth 2 from leaf' => [
                [Config::XML_CATEGORY_DEPTH => '2', Config::XML_CATEGORY_DEPTH_DIRECTION => Config::DEPTH_FROM_LEAF],
                [12],
                ['category' => 'Tops/Jackets'],
            ],
            'excluded ancestor removed from path' => [
                [Config::XML_CATEGORY_EXCLUDED => '11'],
                [12],
                ['category' => 'Men/Jackets'],
            ],
            'excluded deepest category, next available' => [
                [Config::XML_CATEGORY_EXCLUDED => '12', Config::XML_CATEGORY_NEXT_AVAILABLE => '1'],
                [12, 20],
                ['category' => 'Sale'],
            ],
            'excluded deepest category without fallback' => [
                [Config::XML_CATEGORY_EXCLUDED => '12', Config::XML_CATEGORY_NEXT_AVAILABLE => '0'],
                [12, 20],
                [],
            ],
            'only other store root' => [[], [40], []],
        ];
    }

    /**
     * @param array $settings
     * @param int[] $categoryIds
     * @param array $expected
     * @return void
     */
    #[DataProvider('settingsProvider')]
    public function testCategoryData(array $settings, array $categoryIds, array $expected): void
    {
        $this->assertSame(
            $expected,
            $this->createResolver($settings)->getCategoryData($this->product($categoryIds), 1)
        );
    }

    /**
     * @return void
     */
    public function testListIsLoadedInTwoQueries(): void
    {
        $resolver = $this->createResolver([]);
        $products = [$this->product([12]), $this->product([20]), $this->product([11, 12])];
        $resolver->preload($products, 1);
        foreach ($products as $product) {
            $resolver->getCategoryData($product, 1);
        }

        $this->assertSame(2, $this->queries, 'assigned categories, then missing ancestors');
    }
}
