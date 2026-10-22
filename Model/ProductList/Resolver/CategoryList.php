<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\ProductList\Resolver;

use Magento\Catalog\Block\Product\ListProduct;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\View\Element\AbstractBlock;
use Perspective\Zaraz\Api\ProductListResolverInterface;

/**
 * Category page and search results (both render ListProduct with the layer collection)
 */
class CategoryList implements ProductListResolverInterface
{
    /**
     * @param HttpRequest $request
     */
    public function __construct(
        private readonly HttpRequest $request,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function supports(AbstractBlock $block): bool
    {
        return $block instanceof ListProduct;
    }

    /**
     * @inheritDoc
     */
    public function resolve(AbstractBlock $block): ?array
    {
        /** @var ListProduct $block */
        $collection = $block->getLoadedProductCollection();
        $products = array_values($collection->getItems());
        if (!$products) {
            return null;
        }
        $offset = max(0, ((int)$collection->getCurPage() - 1) * (int)$collection->getPageSize());

        if (str_starts_with((string)$this->request->getFullActionName(), 'catalogsearch_')) {
            return ['id' => 'search_results', 'name' => 'Search Results', 'products' => $products, 'offset' => $offset];
        }
        $category = $block->getLayer()->getCurrentCategory();
        return [
            'id' => 'category_' . $category->getId(),
            'name' => (string)$category->getName(),
            'products' => $products,
            'offset' => $offset,
        ];
    }
}
