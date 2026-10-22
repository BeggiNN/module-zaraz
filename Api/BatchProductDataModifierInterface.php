<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Api;

use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Product data modifier that loads its data for all products of a list at once (avoids N+1 queries on listings).
 *
 * preload() is called once per mapped list before modify() is called for each product.
 *
 * @api
 */
interface BatchProductDataModifierInterface extends ProductDataModifierInterface
{
    /**
     * Load data for the products that will be modified next
     *
     * @param ProductInterface[] $products
     * @param int $storeId
     * @return void
     */
    public function preload(array $products, int $storeId): void;
}
