<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Mapper\Modifier\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Perspective\Zaraz\Api\BatchProductDataModifierInterface;
use Perspective\Zaraz\Model\Attribute\AttributeValueResolver;
use Perspective\Zaraz\Model\Config;

/**
 * Admin "Custom attributes" rows: property name => attribute value (option labels for select attributes).
 *
 * The child value is used when a configurable child is selected and has a value, otherwise the parent value.
 */
class CustomAttributes implements BatchProductDataModifierInterface
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
        $attributes = $this->config->getCustomAttributes($storeId);
        if ($attributes) {
            $this->valueResolver->preload($products, array_values($attributes), $storeId);
        }
    }

    /**
     * @inheritDoc
     */
    public function modify(array $data, ProductInterface $product, ?ProductInterface $child, int $storeId): array
    {
        foreach ($this->config->getCustomAttributes($storeId) as $property => $attribute) {
            $value = ($child ? $this->valueResolver->getValue($child, $attribute, $storeId) : null)
                ?? $this->valueResolver->getValue($product, $attribute, $storeId);
            if ($value !== null) {
                $data[$property] = $value;
            }
        }
        return $data;
    }
}
