<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Event\Payload;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Store\Model\StoreManagerInterface;
use Perspective\Zaraz\Model\Mapper\LineResolver;
use Perspective\Zaraz\Model\Mapper\ProductLoader;
use Perspective\Zaraz\Model\Mapper\ProductMapper;
use Perspective\Zaraz\Model\Price\PriceResolver;

/**
 * Payload of single-product catalog events: mapped product at the top level + currency + value
 */
class ProductPayload
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param ProductLoader $productLoader
     * @param LineResolver $lineResolver
     * @param ProductMapper $productMapper
     * @param PriceResolver $priceResolver
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductLoader $productLoader,
        private readonly LineResolver $lineResolver,
        private readonly ProductMapper $productMapper,
        private readonly PriceResolver $priceResolver,
    ) {
    }

    /**
     * Mapped product with catalog price
     *
     * @param ProductInterface $product
     * @param int $storeId
     * @param float|null $qty
     * @return array
     */
    public function build(ProductInterface $product, int $storeId, ?float $qty = null): array
    {
        $store = $this->storeManager->getStore($storeId);
        $this->productLoader->register($product, $storeId);
        $line = $this->lineResolver->fromProduct($product, $store);
        $data = $this->productMapper->map($line, $storeId);
        if ($qty !== null) {
            $data['quantity'] = $qty;
        }
        $data['currency'] = $this->priceResolver->getStoreCurrency($store);
        $data['value'] = $this->priceResolver->round(($data['price'] ?? 0.0) * ($qty ?? 1.0));
        return $data;
    }

    /**
     * Mapped products of a list with catalog prices, in the given order
     *
     * @param ProductInterface[] $products
     * @param int $storeId
     * @return array[]
     */
    public function buildList(array $products, int $storeId): array
    {
        $store = $this->storeManager->getStore($storeId);
        $lines = [];
        foreach ($products as $product) {
            $this->productLoader->register($product, $storeId);
            $lines[] = $this->lineResolver->fromProduct($product, $store);
        }
        return $this->productMapper->mapList($lines, $storeId);
    }

    /**
     * Store currency of catalog prices
     *
     * @param int $storeId
     * @return string
     */
    public function getCurrency(int $storeId): string
    {
        return $this->priceResolver->getStoreCurrency($this->storeManager->getStore($storeId));
    }
}
