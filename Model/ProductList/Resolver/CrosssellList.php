<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\ProductList\Resolver;

use Magento\Checkout\Block\Cart\Crosssell;
use Magento\Framework\View\Element\AbstractBlock;
use Perspective\Zaraz\Api\ProductListResolverInterface;

/**
 * Crosssell Products block
 */
class CrosssellList implements ProductListResolverInterface
{
    /**
     * @inheritDoc
     */
    public function supports(AbstractBlock $block): bool
    {
        return $block instanceof Crosssell;
    }

    /**
     * @inheritDoc
     */
    public function resolve(AbstractBlock $block): ?array
    {
        /** @var Crosssell $block */
        $products = array_values($this->toArray($block->getItems()));
        if (!$products) {
            return null;
        }
        return ['id' => 'crosssell_products', 'name' => 'Crosssell Products', 'products' => $products, 'offset' => 0];
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
