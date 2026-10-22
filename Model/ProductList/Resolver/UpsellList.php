<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\ProductList\Resolver;

use Magento\Catalog\Block\Product\ProductList\Upsell;
use Magento\Framework\View\Element\AbstractBlock;
use Perspective\Zaraz\Api\ProductListResolverInterface;

/**
 * Upsell Products block
 */
class UpsellList implements ProductListResolverInterface
{
    /**
     * @inheritDoc
     */
    public function supports(AbstractBlock $block): bool
    {
        return $block instanceof Upsell;
    }

    /**
     * @inheritDoc
     */
    public function resolve(AbstractBlock $block): ?array
    {
        /** @var Upsell $block */
        $products = array_values($this->toArray($block->getItems()));
        if (!$products) {
            return null;
        }
        return ['id' => 'upsell_products', 'name' => 'Upsell Products', 'products' => $products, 'offset' => 0];
    }

    /**
     * Items of a collection or an array
     *
     * @param iterable|null $items
     * @return array
     */
    private function toArray(?iterable $items): array
    {
        if ($items === null) {
            return [];
        }
        return is_array($items) ? $items : iterator_to_array($items, false);
    }
}
