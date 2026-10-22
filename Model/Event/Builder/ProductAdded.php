<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Event\Builder;

use Perspective\Zaraz\Api\Data\EventContextInterface;
use Perspective\Zaraz\Api\Data\EventInterface;
use Perspective\Zaraz\Api\EventBuilderInterface;
use Perspective\Zaraz\Model\Mapper\LineResolver;
use Perspective\Zaraz\Model\Mapper\ProductMapper;
use Perspective\Zaraz\Model\Price\PriceResolver;
use Magento\Quote\Model\Quote\Item\AbstractItem;

/**
 * "Product Added" — quantity added to the cart (the difference, not the resulting item qty)
 */
class ProductAdded implements EventBuilderInterface
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
     * @inheritDoc
     */
    public function getDefaultName(): string
    {
        return 'Product Added';
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
        $item = $context->getQuoteItem();
        if (!$item instanceof AbstractItem) {
            return null;
        }
        $qty = $context->getQty() ?? (float)$item->getQty();
        if ($qty <= 0) {
            return null;
        }
        $data = $this->productMapper->map($this->lineResolver->fromQuoteItem($item, $qty), $context->getStoreId());
        $data['currency'] = $this->priceResolver->getQuoteCurrency($item);
        $data['value'] = $this->priceResolver->round(($data['price'] ?? 0.0) * $qty);
        $coupon = (string)$item->getQuote()?->getCouponCode();
        if ($coupon !== '') {
            $data['coupon'] = $coupon;
        }
        return $data;
    }
}
