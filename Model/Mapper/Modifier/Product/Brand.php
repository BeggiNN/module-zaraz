<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Mapper\Modifier\Product;

use Perspective\Zaraz\Api\BatchProductDataModifierInterface;
use Perspective\Zaraz\Model\Attribute\AttributeValueResolver;
use Perspective\Zaraz\Model\Config;
use Magento\Catalog\Api\Data\ProductInterface;

/**
 * "brand" from the configured attribute
 */
class Brand implements BatchProductDataModifierInterface
{
    /**
     * @param Config $config
     * @param AttributeValueResolver $valueResolver
     */
    public function __construct(
        private readonly Config $config,
        private readonly AttributeValueResolver $valueResolver,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function preload(array $products, int $storeId): void
    {
        $attribute = $this->config->getBrandAttribute($storeId);
        if ($attribute !== null) {
            $this->valueResolver->preload($products, [$attribute], $storeId);
        }
    }

    /**
     * @inheritDoc
     */
    public function modify(array $data, ProductInterface $product, ?ProductInterface $child, int $storeId): array
    {
        $attribute = $this->config->getBrandAttribute($storeId);
        if ($attribute !== null) {
            $brand = $this->valueResolver->getValue($product, $attribute, $storeId);
            if ($brand !== null) {
                $data['brand'] = $brand;
            }
        }
        return $data;
    }
}
