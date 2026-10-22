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
 * "Product Clicked" — product link in a list (template: the JS adapter sends it on click). Context data: list_id,
 * list_name, position
 */
class ProductClicked implements EventBuilderInterface
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
        return 'Product Clicked';
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
        if ($product === null) {
            return null;
        }
        return array_filter([
            ...$this->productPayload->build($product, $context->getStoreId()),
            'list_id' => $context->getData('list_id'),
            'list_name' => $context->getData('list_name'),
            'position' => $context->getData('position'),
        ], static fn ($value): bool => $value !== null && $value !== '');
    }
}
