<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Event\Builder;

/**
 * "Product Removed" — quantity removed from the cart (whole item, or the decrease of its qty)
 */
class ProductRemoved extends ProductAdded
{
    /**
     * @inheritDoc
     */
    public function getDefaultName(): string
    {
        return 'Product Removed';
    }
}
