<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Mapper\Modifier\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Perspective\Zaraz\Api\ProductDataModifierInterface;
use Perspective\Zaraz\Model\Config;

/**
 * Stock status property ("in_stock" / "out_of_stock").
 *
 * Listing products already carry "is_salable" from the listing collection (MSI or CatalogInventory index), so no
 * query is made for them; other products fall back to Product::isSalable().
 */
class StockStatus implements ProductDataModifierInterface
{
    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function modify(array $data, ProductInterface $product, ?ProductInterface $child, int $storeId): array
    {
        $key = $this->config->getProductPropertyKey('stock_status', $storeId);
        if ($key === null) {
            return $data;
        }
        /** @var Product $subject */
        $subject = $child ?? $product;
        $salable = $subject->hasData('is_salable') ? (bool)$subject->getData('is_salable') : $subject->isSalable();
        $data[$key] = $salable ? 'in_stock' : 'out_of_stock';
        return $data;
    }
}
