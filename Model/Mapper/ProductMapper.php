<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Mapper;

use Perspective\Zaraz\Model\Category\CategoryResolver;
use Perspective\Zaraz\Model\Config;
use Perspective\Zaraz\Model\Mapper\Modifier\ProductDataModifierPool;

/**
 * Maps product lines to the Zaraz product schema (product_id, sku, name, category, variant, price, quantity, ...)
 */
class ProductMapper
{
    /**
     * @param Config $config
     * @param ProductLoader $productLoader
     * @param CategoryResolver $categoryResolver
     * @param ProductDataModifierPool $modifierPool
     */
    public function __construct(
        private readonly Config $config,
        private readonly ProductLoader $productLoader,
        private readonly CategoryResolver $categoryResolver,
        private readonly ProductDataModifierPool $modifierPool,
    ) {
    }

    /**
     * Load products, categories and modifier data of a whole list at once (before mapping it in parts)
     *
     * @param int[] $productIds
     * @param int $storeId
     * @return void
     */
    public function preload(array $productIds, int $storeId): void
    {
        $products = $this->productLoader->load($productIds, $storeId);
        $this->categoryResolver->preload($products, $storeId);
        $this->modifierPool->preload($products, $storeId);
    }

    /**
     * Map one line
     *
     * @param ProductLine $line
     * @param int $storeId
     * @return array
     */
    public function map(ProductLine $line, int $storeId): array
    {
        return $this->mapList([$line], $storeId)[0];
    }

    /**
     * Map several lines with batch loading of products and categories
     *
     * @param ProductLine[] $lines
     * @param int $storeId
     * @return array[] In the order of $lines
     */
    public function mapList(array $lines, int $storeId): array
    {
        $ids = [];
        foreach ($lines as $line) {
            $ids[] = $line->productId;
            if ($line->childId) {
                $ids[] = $line->childId;
            }
        }
        $products = $this->productLoader->load($ids, $storeId);
        $this->categoryResolver->preload($products, $storeId);
        $this->modifierPool->preload($products, $storeId);

        $result = [];
        foreach ($lines as $line) {
            $product = $products[$line->productId] ?? null;
            $child = $line->childId ? ($products[$line->childId] ?? null) : null;

            $data = $this->mapIdentity($line, $product?->getData('sku'), $product?->getName(), $storeId);
            if ($product) {
                $data += $this->categoryResolver->getCategoryData($product, $storeId);
            }
            $data['variant'] = $this->getVariant($line, $storeId);
            $data['price'] = $line->price;
            if ($line->qty !== null) {
                $data['quantity'] = $line->qty;
            }
            if ($product) {
                $data = $this->modifierPool->modify($data, $product, $child, $storeId);
            }
            $result[] = $this->clean($data);
        }
        return $result;
    }

    /**
     * Map product_id / sku / name according to "Product identification"
     *
     * @param ProductLine $line
     * @param string|null $parentSku
     * @param string|null $parentName
     * @param int $storeId
     * @return array
     */
    private function mapIdentity(ProductLine $line, ?string $parentSku, ?string $parentName, int $storeId): array
    {
        $useChild = $line->childId !== null
            && $this->config->getConfigurableMode($storeId) === Config::CONFIGURABLE_CHILD;
        if ($useChild) {
            $id = $line->childId;
            $sku = (string)$line->childSku;
            $name = (string)($line->childName ?: $parentName ?: $line->name);
        } else {
            $id = $line->productId;
            // Quote/order items of configurables carry the child SKU, so prefer the loaded parent SKU
            $sku = (string)($parentSku ?: $line->sku);
            $name = (string)($parentName ?: $line->name);
        }

        $data = [
            'product_id' => $this->config->getProductIdSource($storeId) === Config::ID_SOURCE_ENTITY
                ? (string)$id
                : $sku,
        ];
        if ($this->config->isSendSku($storeId)) {
            $data['sku'] = $sku;
        }
        $data['name'] = trim($name);
        return $data;
    }

    /**
     * Variant of a configurable line according to "Variant source"
     *
     * @param ProductLine $line
     * @param int $storeId
     * @return string|null
     */
    private function getVariant(ProductLine $line, int $storeId): ?string
    {
        if ($line->childId === null || !$this->config->isVariantEnabled($storeId)) {
            return null;
        }
        return match ($this->config->getVariantSource($storeId)) {
            Config::VARIANT_CHILD_SKU => $line->childSku,
            Config::VARIANT_CHILD_NAME => $line->childName,
            default => implode(' / ', array_column($line->options, 'value')) ?: null,
        };
    }

    /**
     * Drop empty values: Zaraz tools treat missing and empty differently (Meta rejects malformed payloads)
     *
     * @param array $data
     * @return array
     */
    private function clean(array $data): array
    {
        return array_filter($data, static fn ($value): bool => $value !== null && $value !== '' && $value !== []);
    }
}
