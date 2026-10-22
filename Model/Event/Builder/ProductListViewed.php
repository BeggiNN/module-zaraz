<?php
/**
 * Copyright © Perspective. All rights reserved.
 */
declare(strict_types=1);

namespace Perspective\Zaraz\Model\Event\Builder;

use Perspective\Zaraz\Api\Data\EventContextInterface;
use Perspective\Zaraz\Api\Data\EventInterface;
use Perspective\Zaraz\Api\EventBuilderInterface;
use Perspective\Zaraz\Model\Event\Payload\ProductPayload;

/**
 * "Product List Viewed" — one chunk of a product list.
 *
 * Context data: products (ProductInterface[] in display order), list_id, list_name, offset (position of the first
 * product minus one). "category" at the event level is the list name: Zaraz GA4 maps it to item_list_name;
 * list_id becomes item_list_id. Items carry position and list_name (GA4 lp/ln).
 */
class ProductListViewed implements EventBuilderInterface
{
    /**
     * @param ProductPayload $productPayload
     */
    public function __construct(
        private readonly ProductPayload $productPayload,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getDefaultName(): string
    {
        return 'Product List Viewed';
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
        $products = (array)$context->getData('products');
        if (!$products) {
            return null;
        }
        $storeId = $context->getStoreId();
        $listName = (string)$context->getData('list_name');
        $offset = (int)$context->getData('offset');

        $items = [];
        foreach ($this->productPayload->buildList(array_values($products), $storeId) as $index => $item) {
            $item['position'] = $offset + $index + 1;
            if ($listName !== '') {
                $item['list_name'] = $listName;
            }
            $items[] = $item;
        }

        return array_filter([
            'list_id' => (string)$context->getData('list_id'),
            'category' => $listName,
            'currency' => $this->productPayload->getCurrency($storeId),
            'products' => $items,
        ], static fn ($value): bool => $value !== '' && $value !== []);
    }
}
