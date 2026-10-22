<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Api;

use Magento\Framework\View\Element\AbstractBlock;

/**
 * Describes the product list rendered by a block (for Product List Viewed / Product Clicked).
 *
 * Register in di.xml: Perspective\Zaraz\Model\ProductList\ResolverPool, argument "resolvers", and declare the
 * plugin Perspective\Zaraz\Plugin\Catalog\AppendListEvents on the block class.
 *
 * @api
 */
interface ProductListResolverInterface
{
    /**
     * Whether the resolver handles this block
     *
     * @param AbstractBlock $block
     * @return bool
     */
    public function supports(AbstractBlock $block): bool;

    /**
     * List data: id, name, products (ProductInterface[] in display order), offset (position of the first product - 1)
     *
     * @param AbstractBlock $block
     * @return array|null Null when the block renders no products
     */
    public function resolve(AbstractBlock $block): ?array;
}
