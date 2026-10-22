<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Event\Payload;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Perspective\Zaraz\Model\Mapper\LineResolver;
use Perspective\Zaraz\Model\Mapper\ProductMapper;
use Perspective\Zaraz\Model\Price\PriceResolver;

/**
 * Payload of cart/checkout events: checkout_id, value, currency, coupon, products
 */
class CartPayload
{
    /**
     * @param LineResolver $lineResolver
     * @param ProductMapper $productMapper
     * @param PriceResolver $priceResolver
     */
    public function __construct(
        private readonly LineResolver $lineResolver,
        private readonly ProductMapper $productMapper,
        private readonly PriceResolver $priceResolver,
    ) {
    }

    /**
     * Cart payload, or null for an empty cart
     *
     * @param CartInterface $quote
     * @return array|null
     */
    public function build(CartInterface $quote): ?array
    {
        /** @var Quote $quote */
        $lines = [];
        $currency = null;
        foreach ($quote->getAllVisibleItems() as $item) {
            $lines[] = $this->lineResolver->fromQuoteItem($item);
            $currency ??= $this->priceResolver->getQuoteCurrency($item);
        }
        if (!$lines) {
            return null;
        }
        $products = $this->productMapper->mapList($lines, (int)$quote->getStoreId());
        $value = 0.0;
        foreach ($products as $product) {
            $value += ($product['price'] ?? 0.0) * ($product['quantity'] ?? 1.0);
        }

        return array_filter([
            'checkout_id' => $quote->getId() ? (string)$quote->getId() : null,
            'value' => $this->priceResolver->round($value),
            'currency' => $currency,
            'coupon' => (string)$quote->getCouponCode() ?: null,
            'products' => $products,
        ], static fn ($value): bool => $value !== null);
    }
}
