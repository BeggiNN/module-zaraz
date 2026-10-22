<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Category;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\Zaraz\Model\Config;

class CategoryResolver
{
    private const MAX_LEVELS = 5;

    /**
     * @var array<int, array<int, array{path: string, level: int, active: bool, name: string}|false>>
     */
    private array $categories = [];

    /**
     * @param CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
    ) {
    }

    /**
     * Preload categories of several products in two queries (assigned categories, then their ancestors)
     *
     * @param ProductInterface[] $products
     * @param int $storeId
     * @return void
     */
    public function preload(array $products, int $storeId): void
    {
        $ids = [];
        foreach ($products as $product) {
            $ids[] = $this->getCategoryIds($product);
        }
        $this->loadCategories(array_merge([], ...$ids), $storeId);
        $ancestors = [];
        foreach ($this->categories[$storeId] ?? [] as $category) {
            if ($category) {
                $ancestors[] = explode('/', $category['path']);
            }
        }
        $this->loadCategories(array_merge([], ...$ancestors), $storeId);
    }

    /**
     * Category properties, e.g. ['category' => 'Men/Tops/Jackets'] or ['category' => 'Men', 'category2' => 'Tops']
     *
     * @param ProductInterface $product
     * @param int $storeId
     * @return array<string, string>
     */
    public function getCategoryData(ProductInterface $product, int $storeId): array
    {
        $names = $this->getNames($product, $storeId);
        if (!$names) {
            return [];
        }
        if ($this->config->getCategoryFormat($storeId) === Config::CATEGORY_FORMAT_PATH) {
            return ['category' => implode($this->config->getCategorySeparator($storeId), $names)];
        }
        $data = [];
        foreach (array_slice($names, 0, self::MAX_LEVELS) as $index => $name) {
            $data[$index === 0 ? 'category' : 'category' . ($index + 1)] = $name;
        }
        return $data;
    }

    /**
     * Name of a single category in the store (list names), or null
     *
     * @param int $categoryId
     * @param int $storeId
     * @return string|null
     */
    public function getCategoryName(int $categoryId, int $storeId): ?string
    {
        $this->loadCategories([$categoryId], $storeId);
        $category = $this->categories[$storeId][$categoryId] ?? false;
        return $category && $category['name'] !== '' ? $category['name'] : null;
    }

    /**
     * Category names from the root to the selected category, after exclusions and depth limit
     *
     * @param ProductInterface $product
     * @param int $storeId
     * @return string[]
     */
    private function getNames(ProductInterface $product, int $storeId): array
    {
        $this->preload([$product], $storeId);
        $category = $this->selectCategory($product, $storeId);
        if ($category === null) {
            return [];
        }
        $excluded = $this->config->getExcludedCategories($storeId);
        $names = [];
        foreach (array_slice(explode('/', $category['path']), 2) as $id) {
            $ancestor = $this->categories[$storeId][(int)$id] ?? false;
            if ($ancestor && $ancestor['name'] !== '' && !in_array((int)$id, $excluded, true)) {
                $names[] = $ancestor['name'];
            }
        }
        $depth = $this->config->getCategoryDepth($storeId);
        if ($depth > 0 && count($names) > $depth) {
            $names = $this->config->getCategoryDepthDirection($storeId) === Config::DEPTH_FROM_LEAF
                ? array_slice($names, -$depth)
                : array_slice($names, 0, $depth);
        }
        return $names;
    }

    /**
     * Deepest active category of the store tree; excluded ones are skipped only with "use next available"
     *
     * @param ProductInterface $product
     * @param int $storeId
     * @return array{path: string, level: int, active: bool, name: string}|null
     */
    private function selectCategory(ProductInterface $product, int $storeId): ?array
    {
        $rootPath = '1/' . $this->storeManager->getStore($storeId)->getRootCategoryId() . '/';
        $candidates = [];
        foreach ($this->getCategoryIds($product) as $id) {
            $category = $this->categories[$storeId][$id] ?? false;
            if ($category && $category['active'] && str_starts_with($category['path'], $rootPath)) {
                $candidates[$id] = $category;
            }
        }
        // Deepest first; equal levels keep the lowest id for a stable result
        uksort($candidates, static fn (int $a, int $b): int => [$candidates[$b]['level'], $a]
            <=> [$candidates[$a]['level'], $b]);

        $excluded = $this->config->getExcludedCategories($storeId);
        foreach ($candidates as $id => $category) {
            if (!in_array($id, $excluded, true)) {
                return $category;
            }
            if (!$this->config->isCategoryUseNextAvailable($storeId)) {
                return null;
            }
        }
        return null;
    }

    /**
     * Category ids assigned to the product
     *
     * @param ProductInterface $product
     * @return int[]
     */
    private function getCategoryIds(ProductInterface $product): array
    {
        return array_map('intval', (array)$product->getCategoryIds());
    }

    /**
     * Load categories that are not cached yet
     *
     * @param int[] $ids
     * @param int $storeId
     * @return void
     */
    private function loadCategories(array $ids, int $storeId): void
    {
        $missing = array_diff(array_unique(array_map('intval', $ids)), array_keys($this->categories[$storeId] ?? []));
        if (!$missing) {
            return;
        }
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId)
            ->addAttributeToSelect(['name', 'is_active'])
            ->addIdFilter($missing);
        foreach ($missing as $id) {
            $this->categories[$storeId][$id] = false;
        }
        foreach ($collection as $category) {
            $this->categories[$storeId][(int)$category->getId()] = [
                'path' => (string)$category->getPath(),
                'level' => (int)$category->getLevel(),
                'active' => (bool)$category->getIsActive(),
                'name' => trim((string)$category->getName()),
            ];
        }
    }
}
