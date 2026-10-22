<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Event\Builder;

use Perspective\Zaraz\Api\Data\EventContextInterface;
use Perspective\Zaraz\Api\Data\EventInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Store\Api\Data\StoreInterface;
use Perspective\Zaraz\Api\EventBuilderInterface;
use Perspective\Zaraz\Model\Attribute\AttributeValueResolver;
use Perspective\Zaraz\Model\Config;
use Perspective\Zaraz\Model\Mapper\ProductLine;
use Perspective\Zaraz\Model\Mapper\LineResolver;
use Perspective\Zaraz\Model\Mapper\ProductLoader;
use Perspective\Zaraz\Model\Mapper\ProductMapper;
use Perspective\Zaraz\Model\Price\PriceResolver;
use Magento\Store\Model\StoreManagerInterface;

/**
 * "Product Viewed" — product detail page
 */
class ProductViewed implements EventBuilderInterface
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param ProductLoader $productLoader
     * @param LineResolver $lineResolver
     * @param ProductMapper $productMapper
     * @param PriceResolver $priceResolver
     * @param Config $config
     * @param AttributeValueResolver $attributeValueResolver
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductLoader $productLoader,
        private readonly LineResolver $lineResolver,
        private readonly ProductMapper $productMapper,
        private readonly PriceResolver $priceResolver,
        private readonly Config $config,
        private readonly AttributeValueResolver $attributeValueResolver,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getDefaultName(): string
    {
        return 'Product Viewed';
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return EventInterface::METHOD_ECOMMERCE;
    }

    /**
     * @inheritDoc
     */
    public function build(EventContextInterface $context): ?array
    {
        $product = $context->getProduct();
        if ($product === null || !$product->getId()) {
            return null;
        }
        $storeId = $context->getStoreId();
        $store = $this->storeManager->getStore($storeId);
        $this->productLoader->register($product, $storeId);

        $data = $this->productMapper->map($this->lineResolver->fromProduct($product, $store), $storeId);
        $data['currency'] = $this->priceResolver->getStoreCurrency($store);
        $data['value'] = $data['price'] ?? 0.0;

        if ($this->config->isViewSendChildren($storeId) && $product->getTypeId() === Configurable::TYPE_CODE) {
            $children = $this->mapChildren($product, $store);
            if ($children) {
                // Zaraz tools use "products" instead of the top-level product when it is present
                $data['products'] = $children;
            }
        }
        return $data;
    }

    /**
     * All salable children of a configurable product as variant lines
     *
     * @param ProductInterface $product
     * @param StoreInterface $store
     * @return array[]
     */
    private function mapChildren(ProductInterface $product, StoreInterface $store): array
    {
        $storeId = (int)$store->getId();
        /** @var Configurable $type */
        $type = $product->getTypeInstance();
        $attributes = [];
        foreach ($type->getConfigurableAttributes($product) as $attribute) {
            $attributes[] = [
                'label' => (string)$attribute->getLabel(),
                'code' => (string)$attribute->getProductAttribute()?->getAttributeCode(),
            ];
        }

        $lines = [];
        foreach ($type->getUsedProducts($product) as $child) {
            if (!$child->isSalable()) {
                continue;
            }
            $this->productLoader->register($child, $storeId);
            $options = [];
            foreach ($attributes as $attribute) {
                $value = $this->attributeValueResolver->getValue($child, $attribute['code'], $storeId);
                if ($value !== null) {
                    $options[] = ['label' => $attribute['label'], 'value' => $value];
                }
            }
            $lines[] = new ProductLine(
                productId: (int)$product->getId(),
                sku: (string)$product->getData('sku'),
                name: (string)$product->getName(),
                price: $this->priceResolver->getProductPrice($child, $store),
                childId: (int)$child->getId(),
                childSku: (string)$child->getSku(),
                childName: (string)$child->getName(),
                options: $options,
            );
        }
        return $this->productMapper->mapList($lines, $storeId);
    }
}
