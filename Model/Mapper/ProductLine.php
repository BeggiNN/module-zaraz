<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Mapper;

/**
 * Normalized product line (PDP product, quote item or order item) before mapping to the Zaraz schema
 */
class ProductLine
{
    /**
     * @param int $productId Parent (configurable) or simple product id
     * @param string $sku Fallback SKU when the product no longer exists
     * @param string $name Fallback name when the product no longer exists
     * @param float $price Unit price, already in the configured currency/tax mode
     * @param float|null $qty
     * @param int|null $childId Selected child of a configurable
     * @param string|null $childSku
     * @param string|null $childName
     * @param array $options Selected configurable options: [['label' => 'Color', 'value' => 'Red'], ...]
     */
    public function __construct(
        public readonly int $productId,
        public readonly string $sku,
        public readonly string $name,
        public readonly float $price,
        public readonly ?float $qty = null,
        public readonly ?int $childId = null,
        public readonly ?string $childSku = null,
        public readonly ?string $childName = null,
        public readonly array $options = [],
    ) {
    }
}
