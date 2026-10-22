<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Mapper;

use Perspective\Zaraz\Model\Config;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

/**
 * Loads products for mapping in one query per batch, with only the attributes the mapping needs
 */
class ProductLoader
{
    /**
     * @var array<int, array<int, ProductInterface|false>>
     */
    private array $cache = [];

    /**
     * @param CollectionFactory $collectionFactory
     * @param Config $config
     * @param string[] $attributes Attributes always selected; other modules can extend via di.xml
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly Config $config,
        private readonly array $attributes = ['name', 'sku', 'price', 'special_price'],
    ) {
    }

    /**
     * Reuse an already loaded product (e.g. the PDP product) instead of loading it again
     *
     * @param ProductInterface $product
     * @param int $storeId
     * @return void
     */
    public function register(ProductInterface $product, int $storeId): void
    {
        $this->cache[$storeId][(int)$product->getId()] = $product;
    }

    /**
     * Products by id; missing (deleted) products are absent from the result
     *
     * Registered products (e.g. listing items) that lack mapping attributes or category ids get them from one
     * additional query for the whole batch.
     *
     * @param int[] $ids
     * @param int $storeId
     * @return array<int, ProductInterface>
     */
    public function load(array $ids, int $storeId): array
    {
        $ids = array_unique(array_filter(array_map('intval', $ids)));
        $attributes = $this->getAttributes($storeId);
        $missing = array_diff($ids, array_keys($this->cache[$storeId] ?? []));
        $incomplete = [];
        foreach (array_intersect($ids, array_keys($this->cache[$storeId] ?? [])) as $id) {
            $product = $this->cache[$storeId][$id];
            if ($product && !$this->isComplete($product, $attributes)) {
                $incomplete[] = $id;
            }
        }

        if ($missing || $incomplete) {
            $collection = $this->collectionFactory->create();
            $collection->setStoreId($storeId)
                ->addIdFilter(array_merge($missing, $incomplete))
                ->addAttributeToSelect($attributes);
            $collection->load();
            $collection->addCategoryIds();
            foreach ($missing as $id) {
                $this->cache[$storeId][$id] = $collection->getItemById($id) ?? false;
            }
            foreach ($incomplete as $id) {
                $loaded = $collection->getItemById($id);
                if ($loaded) {
                    $this->complete($this->cache[$storeId][$id], $loaded, $attributes);
                }
            }
        }

        $result = [];
        foreach ($ids as $id) {
            if ($this->cache[$storeId][$id] ?? false) {
                $result[$id] = $this->cache[$storeId][$id];
            }
        }
        return $result;
    }

    /**
     * Whether the product has all mapping attributes and its category ids
     *
     * @param ProductInterface $product
     * @param string[] $attributes
     * @return bool
     */
    private function isComplete(ProductInterface $product, array $attributes): bool
    {
        $data = $product->getData();
        foreach ([...$attributes, 'category_ids'] as $attribute) {
            if (!array_key_exists($attribute, $data)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Copy missing attributes and category ids from a freshly loaded product
     *
     * @param ProductInterface $product
     * @param ProductInterface $loaded
     * @param string[] $attributes
     * @return void
     */
    private function complete(ProductInterface $product, ProductInterface $loaded, array $attributes): void
    {
        $data = $product->getData();
        foreach ([...$attributes, 'category_ids'] as $attribute) {
            if (!array_key_exists($attribute, $data)) {
                // Null is stored too, so a product without a value is not reloaded again
                $product->setData($attribute, $loaded->getData($attribute));
            }
        }
    }

    /**
     * Attributes to select for the store
     *
     * @param int $storeId
     * @return string[]
     */
    private function getAttributes(int $storeId): array
    {
        $attributes = $this->attributes;
        $brand = $this->config->getBrandAttribute($storeId);
        if ($brand !== null) {
            $attributes[] = $brand;
        }
        foreach ($this->config->getCustomAttributes($storeId) as $attribute) {
            $attributes[] = $attribute;
        }
        return array_values(array_unique($attributes));
    }
}
