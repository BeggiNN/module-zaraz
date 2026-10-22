<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Mapper\Modifier\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Review\Model\ResourceModel\Review\Summary\CollectionFactory;
use Perspective\Zaraz\Api\BatchProductDataModifierInterface;
use Perspective\Zaraz\Model\Config;

/**
 * Review count and rating (0–5, one decimal) from review summaries, one query per list
 */
class Reviews implements BatchProductDataModifierInterface
{
    private const ENTITY_TYPE_PRODUCT = 1;

    /**
     * @var array<int, array<int, array{count: int, rating: float}>>
     */
    private array $summaries = [];

    /**
     * @param Config $config
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly Config $config,
        private readonly CollectionFactory $collectionFactory,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function preload(array $products, int $storeId): void
    {
        if (!$this->isEnabled($storeId)) {
            return;
        }
        $ids = [];
        foreach ($products as $product) {
            $id = (int)$product->getId();
            if (!isset($this->summaries[$storeId][$id])) {
                $ids[] = $id;
                $this->summaries[$storeId][$id] = ['count' => 0, 'rating' => 0.0];
            }
        }
        if (!$ids) {
            return;
        }
        $collection = $this->collectionFactory->create()
            ->addEntityFilter($ids, self::ENTITY_TYPE_PRODUCT)
            ->addStoreFilter($storeId);
        foreach ($collection as $summary) {
            $this->summaries[$storeId][(int)$summary->getData('entity_pk_value')] = [
                'count' => (int)$summary->getData('reviews_count'),
                'rating' => round((float)$summary->getData('rating_summary') / 20, 1),
            ];
        }
    }

    /**
     * @inheritDoc
     */
    public function modify(array $data, ProductInterface $product, ?ProductInterface $child, int $storeId): array
    {
        if (!$this->isEnabled($storeId)) {
            return $data;
        }
        $id = (int)$product->getId();
        if (!isset($this->summaries[$storeId][$id])) {
            $this->preload([$id => $product], $storeId);
        }
        $summary = $this->summaries[$storeId][$id];
        $countKey = $this->config->getProductPropertyKey('review_count', $storeId);
        if ($countKey !== null) {
            $data[$countKey] = $summary['count'];
        }
        $ratingKey = $this->config->getProductPropertyKey('rating', $storeId);
        if ($ratingKey !== null) {
            $data[$ratingKey] = $summary['rating'];
        }
        return $data;
    }

    /**
     * Whether any review property is on
     *
     * @param int $storeId
     * @return bool
     */
    private function isEnabled(int $storeId): bool
    {
        return $this->config->getProductPropertyKey('review_count', $storeId) !== null
            || $this->config->getProductPropertyKey('rating', $storeId) !== null;
    }
}
