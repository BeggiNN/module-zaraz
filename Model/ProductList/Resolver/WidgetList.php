<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\ProductList\Resolver;

use Magento\CatalogWidget\Block\Product\ProductsList;
use Magento\Framework\View\Element\AbstractBlock;
use Perspective\Zaraz\Api\ProductListResolverInterface;

/**
 * "Catalog Products List" CMS widget; list id is stable per widget conditions
 */
class WidgetList implements ProductListResolverInterface
{
    /**
     * @inheritDoc
     */
    public function supports(AbstractBlock $block): bool
    {
        return $block instanceof ProductsList;
    }

    /**
     * @inheritDoc
     */
    public function resolve(AbstractBlock $block): ?array
    {
        /** @var ProductsList $block */
        // Not set when the widget HTML came from the block cache
        $collection = $block->getProductCollection() ?: $block->createCollection();
        $products = array_values($collection->getItems());
        if (!$products) {
            return null;
        }
        $title = trim((string)$block->getData('title'));
        return [
            'id' => 'widget_' . substr(hash('sha256', (string)$block->getData('conditions_encoded') . $title), 0, 10),
            'name' => $title !== '' ? $title : 'Catalog Products List',
            'products' => $products,
            'offset' => 0,
        ];
    }
}
