<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Api;

use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Adds or changes properties of one product in a Zaraz payload.
 *
 * Register in di.xml: Perspective\Zaraz\Model\Mapper\Modifier\ProductDataModifierPool, argument "modifiers".
 * Note: GA4 receives per-item only the spec fields; custom keys reach GA4 only on single-product events
 * (they are written to the event top level there).
 *
 * @api
 */
interface ProductDataModifierInterface
{
    /**
     * Modify mapped product data
     *
     * @param array $data Mapped product (product_id, sku, name, category, variant, price, quantity, ...)
     * @param ProductInterface $product Parent/simple product, loaded for the event store
     * @param ProductInterface|null $child Selected child of a configurable product, if known
     * @param int $storeId
     * @return array
     */
    public function modify(array $data, ProductInterface $product, ?ProductInterface $child, int $storeId): array;
}
