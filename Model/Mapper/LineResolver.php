<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Mapper;

use Perspective\Zaraz\Model\Price\PriceResolver;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Builds ProductLine from a product, a quote item or an order item
 */
class LineResolver
{
    /**
     * @param PriceResolver $priceResolver
     */
    public function __construct(
        private readonly PriceResolver $priceResolver,
    ) {
    }

    /**
     * Line for a catalog product (Product Viewed)
     *
     * @param ProductInterface $product
     * @param StoreInterface $store
     * @return ProductLine
     */
    public function fromProduct(ProductInterface $product, StoreInterface $store): ProductLine
    {
        return new ProductLine(
            productId: (int)$product->getId(),
            sku: (string)$product->getData('sku'),
            name: (string)$product->getName(),
            price: $this->priceResolver->getProductPrice($product, $store),
        );
    }

    /**
     * Line for a visible quote item; totals must be collected
     *
     * @param QuoteItem $item
     * @param float|null $qty Quantity the event is about (null = item qty)
     * @return ProductLine
     */
    public function fromQuoteItem(QuoteItem $item, ?float $qty = null): ProductLine
    {
        $child = null;
        $options = [];
        if ($item->getProductType() === Configurable::TYPE_CODE) {
            $child = $item->getChildren()[0] ?? null;
            $product = $item->getProduct();
            $options = $product
                ? ($product->getTypeInstance()->getOrderOptions($product)['attributes_info'] ?? [])
                : [];
        }

        return new ProductLine(
            productId: (int)$item->getProductId(),
            sku: (string)$item->getSku(),
            name: (string)$item->getName(),
            price: $this->priceResolver->getQuoteItemPrice($item),
            qty: $qty ?? (float)$item->getQty(),
            childId: $child ? (int)$child->getProductId() : null,
            childSku: $child ? (string)$child->getSku() : null,
            childName: $child ? (string)$child->getName() : null,
            options: $this->normalizeOptions($options),
        );
    }

    /**
     * Line for a visible order item
     *
     * @param OrderItemInterface $item
     * @param float|null $qty quantity instead of the ordered one (refunded / cancelled quantity)
     * @return ProductLine
     */
    public function fromOrderItem(OrderItemInterface $item, ?float $qty = null): ProductLine
    {
        $child = null;
        $options = [];
        if ($item->getProductType() === Configurable::TYPE_CODE) {
            $child = $item->getChildrenItems()[0] ?? null;
            $options = $item->getProductOptions()['attributes_info'] ?? [];
        }

        return new ProductLine(
            productId: (int)$item->getProductId(),
            sku: (string)$item->getSku(),
            name: (string)$item->getName(),
            price: $this->priceResolver->getOrderItemPrice($item),
            qty: $qty ?? (float)$item->getQtyOrdered(),
            childId: $child ? (int)$child->getProductId() : null,
            childSku: $child ? (string)$child->getSku() : null,
            childName: $child ? (string)$child->getName() : null,
            options: $this->normalizeOptions($options),
        );
    }

    /**
     * Keep only label/value pairs
     *
     * @param array $options
     * @return array<int, array{label: string, value: string}>
     */
    private function normalizeOptions(array $options): array
    {
        $result = [];
        foreach ($options as $option) {
            if (isset($option['value']) && (string)$option['value'] !== '') {
                $result[] = ['label' => (string)($option['label'] ?? ''), 'value' => (string)$option['value']];
            }
        }
        return $result;
    }
}
