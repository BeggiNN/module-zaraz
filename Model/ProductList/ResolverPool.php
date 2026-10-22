<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\ProductList;

use Magento\Framework\View\Element\AbstractBlock;
use Perspective\Zaraz\Api\ProductListResolverInterface;

/**
 * Product list resolvers in di.xml order; the first one that supports the block wins
 */
class ResolverPool
{
    /**
     * @param ProductListResolverInterface[] $resolvers
     */
    public function __construct(
        private readonly array $resolvers = [],
    ) {
    }

    /**
     * List data of the block or null
     *
     * @param AbstractBlock $block
     * @return array|null
     */
    public function resolve(AbstractBlock $block): ?array
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($block)) {
                return $resolver->resolve($block);
            }
        }
        return null;
    }
}
