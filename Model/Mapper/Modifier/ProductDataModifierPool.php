<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Mapper\Modifier;

use Perspective\Zaraz\Api\BatchProductDataModifierInterface;
use Perspective\Zaraz\Api\ProductDataModifierInterface;
use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Runs product data modifiers registered in di.xml
 */
class ProductDataModifierPool
{
    /**
     * @param ProductDataModifierInterface[] $modifiers
     */
    public function __construct(
        private readonly array $modifiers = [],
    ) {
    }

    /**
     * Let batch modifiers load data for all products of a list
     *
     * @param ProductInterface[] $products
     * @param int $storeId
     * @return void
     */
    public function preload(array $products, int $storeId): void
    {
        foreach ($this->modifiers as $modifier) {
            if ($modifier instanceof BatchProductDataModifierInterface) {
                $modifier->preload($products, $storeId);
            }
        }
    }

    /**
     * Apply all modifiers
     *
     * @param array $data
     * @param ProductInterface $product
     * @param ProductInterface|null $child
     * @param int $storeId
     * @return array
     */
    public function modify(array $data, ProductInterface $product, ?ProductInterface $child, int $storeId): array
    {
        foreach ($this->modifiers as $modifier) {
            $data = $modifier->modify($data, $product, $child, $storeId);
        }
        return $data;
    }
}
